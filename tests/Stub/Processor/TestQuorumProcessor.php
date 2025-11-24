<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\Stub\Processor;

use MessageBusBundle\AmqpTools\QueueType;
use MessageBusBundle\EnqueueProcessor\AbstractProcessor;

class TestQuorumProcessor extends AbstractProcessor
{
    public const QUEUE = 'test.quorum.queue';

    public function getSubscribedRoutingKeys(): array
    {
        return [
            self::QUEUE => ['test.quorum.routing'],
        ];
    }

    public function getQueueType(): QueueType
    {
        return QueueType::QUORUM;
    }

    public function doProcess($body, \Interop\Queue\Message $message, \Interop\Queue\Context $session): string
    {
        return self::ACK;
    }
}
