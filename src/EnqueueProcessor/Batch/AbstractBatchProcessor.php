<?php

declare(strict_types=1);

namespace MessageBusBundle\EnqueueProcessor\Batch;

use Enqueue\Consumption\Result as EnqueueResult;
use Interop\Amqp\AmqpMessage;
use Interop\Queue\Context;
use Interop\Queue\Message as InteropMessage;
use MessageBusBundle\EnqueueProcessor\ExceptionHandler\ChainExceptionHandler;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;
use MessageBusBundle\Events;
use MessageBusBundle\Exception\InterruptProcessingException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractBatchProcessor implements BatchProcessorInterface, ProcessorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const BATCH_PROCESSOR_TAG = 'messagesbus.batch_processor';

    protected EventDispatcherInterface $eventDispatcher;
    protected ChainExceptionHandler $chainExceptionHandler;

    #[Required]
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): self
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }

    #[Required]
    public function setChainExceptionHandler(ChainExceptionHandler $chainExceptionHandler): self
    {
        $this->chainExceptionHandler = $chainExceptionHandler;

        return $this;
    }

    /**
     * @param InteropMessage[]
     *
     * @return string[]
     *
     * @throws InterruptProcessingException
     */
    public function process(array $messagesBatch, Context $context): array
    {
        try {
            $event = new Events\BatchConsumeEvent($messagesBatch, $context, static::class);
            $this->eventDispatcher->dispatch($event, Events::BATCH_CONSUME__START);

            try {
                $results = $this->doProcess($messagesBatch, $context);
            } catch (InterruptProcessingException $exception) {
                $results = $exception->getResult();
                $interruptException = $exception;
            }

            $this->eventDispatcher->dispatch($event, Events::BATCH_CONSUME__FINISHED);

            $processedResult = array_map(
                function (Result $result) use ($messagesBatch, $context): Result {
                    if (self::REQUEUE === $result->getOpResult()) {
                        $resultAfterExceptionHandling = $this->chainExceptionHandler->handle(
                            $this,
                            $result->getException(),
                            $messagesBatch[$result->getDeliveryTag()],
                            $context
                        );

                        switch ($resultAfterExceptionHandling) {
                            case EnqueueResult::ACK:
                                return Result::ack($result->getDeliveryTag());
                            case EnqueueResult::REJECT:
                                return Result::reject($result->getDeliveryTag());
                        }
                    }

                    return $result;
                },
                $results
            );
        } catch (\Throwable $exception) {
            $event = new Events\BatchExceptionConsumeEvent($exception, $messagesBatch, $context, static::class);
            $this->eventDispatcher->dispatch($event, Events::BATCH_CONSUME__EXCEPTION);

            foreach ($messagesBatch as $message) {
                $this->chainExceptionHandler->handle($this, $exception, $message, $context);
            }

            return [];
        }

        if (isset($interruptException) && $interruptException instanceof InterruptProcessingException) {
            $interruptException->setResult($processedResult);

            throw $interruptException;
        }

        return $processedResult;
    }

    /**
     * @param AmqpMessage[] $messages
     *
     * @return Result[]
     */
    abstract public function doProcess(array $messages, Context $session): array;

    /**
     * @codeCoverageIgnore
     */
    protected static function getProcessorServiceKey(): string
    {
        return self::class;
    }

    /**
     * @codeCoverageIgnore
     */
    final public static function getDefaultIndexName(): string
    {
        return self::getProcessorServiceKey();
    }
}
