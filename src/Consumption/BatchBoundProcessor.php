<?php

namespace MessageBusBundle\Consumption;

use Interop\Queue\Queue;
use MessageBusBundle\EnqueueProcessor\Batch\BatchProcessorInterface;

final class BatchBoundProcessor
{
    private Queue $queue;
    private BatchProcessorInterface $processor;

    public function __construct(Queue $queue, BatchProcessorInterface $processor)
    {
        $this->queue = $queue;
        $this->processor = $processor;
    }

    public function getQueue(): Queue
    {
        return $this->queue;
    }

    public function getProcessor(): BatchProcessorInterface
    {
        return $this->processor;
    }
}
