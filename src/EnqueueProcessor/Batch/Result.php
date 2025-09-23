<?php

namespace MessageBusBundle\EnqueueProcessor\Batch;

use Enqueue\Consumption\Result as EnqueueResult;

class Result
{
    private int $deliveryTag;
    private string $opResult;
    private ?\Throwable $exception;

    private function __construct(int $deliveryTag, string $opResult, ?\Throwable $exception = null)
    {
        $this->deliveryTag = $deliveryTag;
        $this->opResult = $opResult;
        $this->exception = $exception;
    }

    public static function ack(int $deliveryTag): self
    {
        return new self($deliveryTag, EnqueueResult::ACK);
    }

    public static function reject(int $deliveryTag): self
    {
        return new self($deliveryTag, EnqueueResult::REJECT);
    }

    public static function requeue(int $deliveryTag, \Throwable $exception): self
    {
        return new self($deliveryTag, EnqueueResult::REQUEUE, $exception);
    }

    public function getDeliveryTag(): int
    {
        return $this->deliveryTag;
    }

    public function getOpResult(): string
    {
        return $this->opResult;
    }

    public function getException(): ?\Throwable
    {
        return $this->exception;
    }
}
