<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\Stub\Processor;

use Interop\Queue\Context;
use MessageBusBundle\EnqueueProcessor\Batch\BatchProcessorInterface;

class NonBatchProcessor implements BatchProcessorInterface
{
    public function process(array $messagesBatch, Context $context): array
    {
        return [];
    }

    public function getSubscribedRoutingKeys(): array
    {
        return [];
    }
}
