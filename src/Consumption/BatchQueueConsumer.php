<?php

namespace MessageBusBundle\Consumption;

use Enqueue\Consumption\Context\InitLogger;
use Enqueue\Consumption\Exception\InvalidArgumentException;
use Enqueue\Consumption\Exception\LogicException;
use Enqueue\Consumption\FallbackSubscriptionConsumer;
use Enqueue\Consumption\Result;
use Interop\Amqp\AmqpMessage;
use Interop\Queue\Consumer;
use Interop\Queue\Context as InteropContext;
use Interop\Queue\Exception\SubscriptionConsumerNotSupportedException;
use Interop\Queue\Queue as InteropQueue;
use Interop\Queue\SubscriptionConsumer;
use MessageBusBundle\EnqueueProcessor\Batch\BatchProcessorInterface;
use MessageBusBundle\EnqueueProcessor\Batch\Result as BatchResult;
use MessageBusBundle\Exception\InterruptProcessingException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class BatchQueueConsumer implements BatchQueueConsumerInterface
{
    private InteropContext $interopContext;
    private LoggerInterface $logger;
    private SubscriptionConsumer $fallbackSubscriptionConsumer;

    /**
     * @var BatchBoundProcessor[]
     */
    private array $boundProcessors;

    /**
     * @var AmqpMessage[]
     */
    private array $messageBatch = [];

    private int $receiveTimeout;

    private int $batchSize;

    public function __construct(
        array $interopContexts,
        array $boundProcessors = [],
        LoggerInterface|null $logger = null,
        int $receiveTimeout = 10000,
        int $batchSize = PHP_INT_MAX
    ) {
        $this->interopContext = reset($interopContexts);
        $this->receiveTimeout = $receiveTimeout;

        $this->logger = $logger ?: new NullLogger();

        $this->boundProcessors = [];
        array_walk(
            $boundProcessors,
            function (BatchBoundProcessor $processor) {
                $this->boundProcessors[] = $processor;
            }
        );

        $this->fallbackSubscriptionConsumer = new FallbackSubscriptionConsumer();
        $this->batchSize = $batchSize;
    }

    public function setReceiveTimeout(int $timeout): void
    {
        $this->receiveTimeout = $timeout;
    }

    public function getReceiveTimeout(): int
    {
        return $this->receiveTimeout;
    }

    public function getContext(): InteropContext
    {
        return $this->interopContext;
    }

    public function bind($queueName, BatchProcessorInterface $processor): BatchQueueConsumerInterface
    {
        if (is_string($queueName)) {
            $queueName = $this->interopContext->createQueue($queueName);
        }

        InvalidArgumentException::assertInstanceOf($queueName, InteropQueue::class);

        if (empty($queueName->getQueueName())) {
            throw new LogicException('The queue name must be not empty.');
        }
        if (array_key_exists($queueName->getQueueName(), $this->boundProcessors)) {
            throw new LogicException(sprintf('The queue was already bound. Queue: %s', $queueName->getQueueName()));
        }

        $this->boundProcessors[$queueName->getQueueName()] = new BatchBoundProcessor($queueName, $processor);

        return $this;
    }

    public function bindCallback($queueName, callable $processor): BatchQueueConsumerInterface
    {
        return $this->bind($queueName, new BatchCallbackProcessor($processor));
    }

    public function consume(): void
    {
        $initLogger = new InitLogger($this->logger);
        $this->logger = $initLogger->getLogger();

        if (empty($this->boundProcessors)) {
            throw new \LogicException(
                'There is nothing to consume. It is required to bind something before calling consume method.'
            );
        }

        /** @var Consumer[] $consumers */
        $consumers = [];
        foreach ($this->boundProcessors as $boundProcessor) {
            $queue = $boundProcessor->getQueue();

            $consumers[$queue->getQueueName()] = $this->interopContext->createConsumer($queue);
        }

        try {
            $subscriptionConsumer = $this->interopContext->createSubscriptionConsumer();
        } catch (SubscriptionConsumerNotSupportedException $e) {
            $subscriptionConsumer = $this->fallbackSubscriptionConsumer;
        }

        $executionInterrupted = false;

        foreach ($consumers as $consumer) {
            $callback = function (AmqpMessage $message) use ($consumer, &$executionInterrupted) {
                $this->messageBatch[$message->getDeliveryTag()] = $message;

                if (count($this->messageBatch) >= $this->batchSize) {
                    try {
                        $this->processBatch($consumer);
                    } catch (InterruptProcessingException $exception) {
                        $executionInterrupted = true;

                        return false;
                    }
                }

                return true;
            };


            $subscriptionConsumer->subscribe($consumer, $callback);
        }

        while (true) {
            if ($executionInterrupted) {
                return;
            }

            $subscriptionConsumer->consume($this->receiveTimeout);

            foreach ($consumers as $consumer) {
                if (!empty($this->messageBatch)) {
                    try {
                        $this->processBatch($consumer);
                    } catch (InterruptProcessingException $e) {
                        return;
                    }
                }
            }
        }
    }

    /**
     * @param SubscriptionConsumer $fallbackSubscriptionConsumer
     * @internal
     *
     */
    public function setFallbackSubscriptionConsumer(SubscriptionConsumer $fallbackSubscriptionConsumer): void
    {
        $this->fallbackSubscriptionConsumer = $fallbackSubscriptionConsumer;
    }

    /**
     * @throws InterruptProcessingException
     */
    private function processBatch(Consumer $consumer): bool
    {
        $queue = $consumer->getQueue();
        if (false === array_key_exists($queue->getQueueName(), $this->boundProcessors)) {
            throw new \LogicException(
                sprintf('The processor for the queue "%s" could not be found.', $queue->getQueueName())
            );
        }

        $processor = $this->boundProcessors[$queue->getQueueName()]->getProcessor();

        try {
            $result = $processor->process($this->messageBatch, $this->interopContext);
        } catch (InterruptProcessingException $exception) {
            $result = $exception->getResult();
        }

        foreach ($result as $messageRes) {
            /** @var BatchResult $messageRes */
            switch ($messageRes->getOpResult()) {
                case Result::ACK:
                    $consumer->acknowledge($this->messageBatch[$messageRes->getDeliveryTag()]);
                    unset($this->messageBatch[$messageRes->getDeliveryTag()]);
                    break;
                case Result::REJECT:
                    $consumer->reject($this->messageBatch[$messageRes->getDeliveryTag()]);
                    unset($this->messageBatch[$messageRes->getDeliveryTag()]);
                    break;
                case Result::REQUEUE:
                    $consumer->reject($this->messageBatch[$messageRes->getDeliveryTag()], true);
                    unset($this->messageBatch[$messageRes->getDeliveryTag()]);
                    break;
                case Result::ALREADY_ACKNOWLEDGED:
                    break;
                default:
                    throw new \LogicException(sprintf('Status is not supported: %s', $messageRes->getOpResult()));
            }
        }

        if (!empty($this->messageBatch)) {
            foreach ($this->messageBatch as $amqpMessage) {
                $consumer->reject($amqpMessage);
            }

            $this->messageBatch = [];
        }

        if (isset($exception) && $exception instanceof InterruptProcessingException) {
            throw $exception;
        }

        return true;
    }
}
