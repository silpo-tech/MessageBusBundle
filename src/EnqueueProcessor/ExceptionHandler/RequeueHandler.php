<?php

declare(strict_types=1);

namespace MessageBusBundle\EnqueueProcessor\ExceptionHandler;

use Enqueue\Client\Config;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;
use MessageBusBundle\Events\ConsumeRequeueEvent;
use MessageBusBundle\Events\ConsumeRequeueExceedEvent;
use MessageBusBundle\Exception\RequeueException;
use MessageBusBundle\Producer\ProducerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class RequeueHandler implements ExceptionHandlerInterface
{
    use ExceptionTraceGeneratorTrait;

    private const REQUEUE_COUNT_KEY = 'requeue-count';
    private const REQUEUE_REASON = 'requeue-reason';
    private const REQUEUE_STACK_TRACE = 'requeue-trace';

    private const REQUEUE_FAILED_QUEUE = 'failed';

    private const MESSAGE_REQUEUE = '[MessageBusBundle] Requeue message';
    private const MESSAGE_REQUEUE_EXCEED = '[MessageBusBundle] Requeue message: exceed requeue count';

    public function __construct(
        private readonly ProducerInterface $producer,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @param RequeueException $exception
     * @param Message $message
     * @param Context $context
     * @param ProcessorInterface $processor
     * @return string
     */
    public function handle(
        Throwable $exception,
        Message $message,
        Context $context,
        ProcessorInterface $processor,
    ): string {
        $count = $message->getProperty(self::REQUEUE_COUNT_KEY, 0);
        $count++;
        $message->setProperty(self::REQUEUE_COUNT_KEY, $count);
        $message->setProperty(self::REQUEUE_REASON, $exception->getMessage());

        $logMessage = [
            'reason' => $exception->getMessage(),
            'trace' => $this->getExceptionTraceTrait($exception),
            'message' => $message->getBody(),
            'correlationId' => (string)$message->getCorrelationId()
        ];

        $queueName = $this->getQueueName($processor);
        if ($count > $exception->getCount()) {
            $this->producer->sendMessageToQueue($this->createFailedQueueName($queueName), $message);
            $this->eventDispatcher->dispatch(
                new ConsumeRequeueExceedEvent($exception, $message, $context, $processor::class)
            );

            $this->logger->error(self::MESSAGE_REQUEUE_EXCEED, $logMessage);
        } else {
            $this->producer->sendMessageToQueue($queueName, $message, pow(2, $count) * 1000);
            $this->eventDispatcher->dispatch(
                new ConsumeRequeueEvent($exception, $message, $context, $processor::class, $count)
            );

            $this->logger->debug(self::MESSAGE_REQUEUE, $logMessage);
        }

        return Processor::ACK;
    }

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof RequeueException;
    }

    protected function createFailedQueueName(string $queueName): string
    {
        return $queueName . $this->config->getSeparator() . self::REQUEUE_FAILED_QUEUE;
    }

    private function getQueueName(ProcessorInterface $processor): string
    {
        return array_keys($processor->getSubscribedRoutingKeys())[0];
    }
}
