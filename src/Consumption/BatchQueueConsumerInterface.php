<?php

namespace MessageBusBundle\Consumption;

use Enqueue\Consumption\ExtensionInterface;
use Interop\Queue\Context;
use Interop\Queue\Queue;
use MessageBusBundle\EnqueueProcessor\Batch\BatchProcessorInterface;

interface BatchQueueConsumerInterface
{
    public function setReceiveTimeout(int $timeout): void;
    public function getReceiveTimeout(): int;
    public function getContext(): Context;

    /**
     * @param string|Queue $queueName
     */
    public function bind($queueName, BatchProcessorInterface $processor): self;

    /**
     * @param string|Queue $queueName
     */
    public function bindCallback($queueName, callable $processor): self;

    public function consume(): void;
}
