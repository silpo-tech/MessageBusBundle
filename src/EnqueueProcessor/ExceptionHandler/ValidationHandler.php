<?php

declare(strict_types=1);

namespace MessageBusBundle\EnqueueProcessor\ExceptionHandler;

use Enqueue\Client\Config;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;
use MessageBusBundle\Exception\ValidationException;
use MessageBusBundle\Producer\ProducerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Throwable;

class ValidationHandler implements ExceptionHandlerInterface
{
    private const QUEUE_SUFFIX = 'failed';

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
        /** @var ValidationException $exception */
        $message = [
            'reason' => $exception->getMessage(),
            'violations' => $this->adoptViolations($exception->getViolations()),
            'message' => json_decode($message->getBody()),
            'correlationId' => (string) $message->getCorrelationId()
        ];

        $this->producer->sendToQueue(
            $this->createQueueName(),
            json_encode($message, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE)
        );

        $this->logger->debug($exception->getMessage(), $message);

        return Processor::ACK;
    }

    public function supports(\Throwable $exception): bool
    {
        return $exception instanceof ValidationException;
    }

    protected function createQueueName(): string
    {
        return $this->config->getApp() . $this->config->getSeparator() . self::QUEUE_SUFFIX;
    }

    private function adoptViolations(iterable $violations)
    {
        $result = [];
        foreach ($violations as $key => $value) {
            if (is_iterable($value)) {
                $result[$key] = $this->adoptViolations($value);
                continue;
            }
            if ($value instanceof ConstraintViolation) {
                $result[$key][$value->getPropertyPath()] = $value->getMessage();
                continue;
            }
            $result[$key] = $value;
        }

        return $result;
    }
}
