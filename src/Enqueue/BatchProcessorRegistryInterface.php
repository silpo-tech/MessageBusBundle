<?php

declare(strict_types=1);

namespace MessageBusBundle\Enqueue;

use MessageBusBundle\EnqueueProcessor\Batch\BatchProcessorInterface;

interface BatchProcessorRegistryInterface
{
    public function get(string $processorName): BatchProcessorInterface;
}
