<?php

namespace MessageBusBundle\Enqueue;

use MessageBusBundle\EnqueueProcessor\Batch\BatchProcessorInterface;

interface BatchProcessorRegistryInterface
{
    public function get(string $processorName): BatchProcessorInterface;
}
