<?php

declare(strict_types=1);

namespace MessageBusBundle\EnqueueProcessor\ExceptionHandler;

use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;
use Psr\Log\LoggerInterface;
use Enqueue\Client\Config;
use MessageBusBundle\Producer\ProducerInterface;
use Throwable;

class FailConsumeHandler implements ExceptionHandlerInterface
{
    use ExceptionTraceGeneratorTrait;

    private const QUEUE_SUFFIX = 'exception.failed';

    private const MESSAGE_FAIL_CONSUME = '[MessageBusBundle] Fail consume message';

    private ProducerInterface $producer;

    private LoggerInterface $logger;

    private Config $config;

    public function __construct(ProducerInterface $producer, LoggerInterface $logger, Config $config)
    {
        $this->logger = $logger;
        $this->producer = $producer;
        $this->config = $config;
    }

    public function handle(
        Throwable $exception,
        Message $message,
        Context $context,
        ProcessorInterface $processor,
    ): string {
        $logMessage = [
            'reason' => $exception->getMessage(),
            'trace' => $this->getExceptionTraceTrait($exception),
            'message' => $message->getBody(),
        ];
        $logMessage = array_merge($logMessage, $message->getProperties(), $message->getHeaders());

        try {
            $message->setProperty('x-exception-message', $exception->getMessage());
            $message->setProperty('x-exception-file', $exception->getFile());
            $message->setProperty('x-exception-line', $exception->getLine());
            $message->setProperty(
                'x-exception-trace',
                json_encode(
                    $this->getExceptionTraceTrait($exception),
                    JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE,
                )
            );

            $this->producer->sendMessageToQueue($this->createQueueName($processor), $message);

            $this->logger->error(self::MESSAGE_FAIL_CONSUME, $logMessage);
        } catch (Throwable $exception) {
            $this->logger->error(
                self::MESSAGE_FAIL_CONSUME,
                array_merge($logMessage, ['handleException' => $exception])
            );
        }

        return Processor::REJECT;
    }

    public function supports(Throwable $exception): bool
    {
        return true;
    }

    protected function createQueueName(ProcessorInterface $processor): string
    {
        $originalQueueName = array_keys($processor->getSubscribedRoutingKeys())[0];

        return $originalQueueName . $this->config->getSeparator() . self::QUEUE_SUFFIX;
    }
}
