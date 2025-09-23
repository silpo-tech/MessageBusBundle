<?php

declare(strict_types=1);

namespace MessageBusBundle\EnqueueProcessor\Batch;

use Interop\Amqp\AmqpMessage;
use MessageBusBundle\Exception\RequeueException;

abstract class AbstractBatchContext
{
    /** @var Result[] */
    protected array $batchResult = [];

    /**
     * @return Result[]
     */
    public function getBatchResult(): array
    {
        return $this->batchResult;
    }

    public function reject(AmqpMessage $amqpMessage): self
    {
        $this->batchResult[$amqpMessage->getDeliveryTag()] = $this->rejectMessage($amqpMessage);

        return $this;
    }

    public function ack(AmqpMessage $amqpMessage): self
    {
        $this->batchResult[$amqpMessage->getDeliveryTag()] = $this->ackMessage($amqpMessage);

        return $this;
    }

    public function requeue(AmqpMessage $amqpMessage, RequeueException $exception): self
    {
        $this->batchResult[$amqpMessage->getDeliveryTag()] = $this->requeueMessage($amqpMessage, $exception);

        return $this;
    }

    protected function rejectMessage(AmqpMessage $amqpMessage): Result
    {
        return Result::reject($amqpMessage->getDeliveryTag());
    }

    protected function ackMessage(AmqpMessage $amqpMessage): Result
    {
        return Result::ack($amqpMessage->getDeliveryTag());
    }

    protected function requeueMessage(AmqpMessage $amqpMessage, RequeueException $exception): Result
    {
        return Result::requeue($amqpMessage->getDeliveryTag(), $exception);
    }
}
