<?php

declare(strict_types=1);

namespace MessageBusBundle\EnqueueProcessor\ExceptionHandler;

use Enqueue\Client\Config;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use MessageBusBundle\EnqueueProcessor\OverridesRetryConfigInterface;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;
use MessageBusBundle\Events\ConsumeRequeueEvent;
use MessageBusBundle\Events\ConsumeRequeueExceedEvent;
use MessageBusBundle\Exception\RequeueException;
use MessageBusBundle\Producer\ProducerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class RequeueHandler implements ExceptionHandlerInterface
{
    use ExceptionTraceGeneratorTrait;

    private const REQUEUE_COUNT_KEY = 'requeue-count';
    private const REQUEUE_REASON = 'requeue-reason';
    private const REQUEUE_EXCEPTION_CLASS = 'requeue-previous-exception-class';
    private const REQUEUE_EXCEPTION_MESSAGE = 'requeue-previous-exception-message';
    private const REQUEUE_EXCEPTION_TRACE = 'requeue-previous-exception-trace';
    private const REQUEUE_FAILED_QUEUE = 'failed';
    private const MESSAGE_REQUEUE = '[MessageBusBundle] Requeue message';
    private const MESSAGE_REQUEUE_EXCEED = '[MessageBusBundle] Requeue message: exceed requeue count';

    public function __construct(
        private readonly ProducerInterface $producer,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NonRetryableExceptionChecker $nonRetryableExceptionChecker,
        #[Autowire(param: 'messagebus.requeue.max_retries')]
        private readonly int $maxRetries = 0,
        #[Autowire(param: 'messagebus.requeue.expose_previous_exception')]
        private readonly bool $exposePreviousException = false,
    ) {
    }

    public function handle(
        \Throwable $exception,
        Message $message,
        Context $context,
        ProcessorInterface $processor,
    ): string {
        $maxRetries = $this->resolveMaxRetries($exception, $processor);

        if (!$exception instanceof RequeueException) {
            $exception = new RequeueException($exception->getMessage(), $maxRetries, $exception);
        }

        $count = $message->getProperty(self::REQUEUE_COUNT_KEY, 0);
        ++$count;
        $message->setProperty(self::REQUEUE_COUNT_KEY, $count);

        $original = $this->exposePreviousException && null !== $exception->getPrevious()
            ? $exception->getPrevious()
            : $exception;

        $message->setProperty(self::REQUEUE_REASON, $exception->getMessage());

        $logMessage = [
            'reason' => $original->getMessage(),
            'trace' => $this->getExceptionTraceTrait($original),
            'message' => $message->getBody(),
            'correlationId' => (string) $message->getCorrelationId(),
        ];

        $queueName = $this->getQueueName($processor);
        if ($count > $maxRetries) {
            $message->setProperty(self::REQUEUE_EXCEPTION_CLASS, $original::class);
            $message->setProperty(self::REQUEUE_EXCEPTION_MESSAGE, $original->getMessage());
            $message->setProperty(self::REQUEUE_EXCEPTION_TRACE, $this->getExceptionTraceTrait($original));
            $this->producer->sendMessageToQueue(
                $this->createFailedQueueName($queueName),
                $message,
                queueType: $processor->getQueueType(),
            );
            $this->eventDispatcher->dispatch(
                new ConsumeRequeueExceedEvent($exception, $message, $context, $processor::class)
            );

            $this->logger->error(self::MESSAGE_REQUEUE_EXCEED, $logMessage);
        } else {
            $this->producer->sendMessageToQueue(
                $queueName,
                $message,
                pow(2, $count) * 1000,
                $processor->getQueueType(),
            );
            $this->eventDispatcher->dispatch(
                new ConsumeRequeueEvent($exception, $message, $context, $processor::class, $count)
            );

            $this->logger->debug(self::MESSAGE_REQUEUE, $logMessage);
        }

        return Processor::ACK;
    }

    public function supports(\Throwable $exception, ?ProcessorInterface $processor = null): bool
    {
        if ($this->nonRetryableExceptionChecker->isNonRetryable($exception)) {
            return false;
        }

        return $this->resolveMaxRetries($exception, $processor) > 0;
    }

    protected function createFailedQueueName(string $queueName): string
    {
        return $queueName.$this->config->getSeparator().self::REQUEUE_FAILED_QUEUE;
    }

    private function resolveMaxRetries(\Throwable $exception, ?ProcessorInterface $processor): int
    {
        return match (true) {
            $exception instanceof RequeueException => $exception->getCount(),
            $processor instanceof OverridesRetryConfigInterface => $processor->getMaxRetries(),
            default => $this->maxRetries,
        };
    }

    private function getQueueName(ProcessorInterface $processor): string
    {
        return array_keys($processor->getSubscribedRoutingKeys())[0];
    }
}
