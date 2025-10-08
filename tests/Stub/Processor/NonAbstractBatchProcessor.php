<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\Stub\Processor;

use Interop\Queue\Context;
use MessageBusBundle\EnqueueProcessor\Batch\BatchProcessorInterface;

class NonAbstractBatchProcessor implements BatchProcessorInterface
{
    public const QUEUE = 'test.non.abstract.batch.queue';

    public function process(array $messages, Context $context): array
    {
        return [];
    }

    public function getSubscribedRoutingKeys(): array
    {
        return [
            self::QUEUE => ['test.non.abstract.batch.routing'],
        ];
    }
}
