<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\Stub\Processor;

use Interop\Queue\Context;
use Interop\Queue\Message;
use MessageBusBundle\EnqueueProcessor\AbstractProcessor;

class TestProcessor extends AbstractProcessor
{
    public const QUEUE = 'test.queue';

    public function doProcess($body, Message $message, Context $session): string
    {
        // Throw exception to prevent infinite consumption in tests
        throw new \RuntimeException('TestProcessor executed - stopping consumption');
    }

    public function process(Message $message, Context $context): string
    {
        return $this->doProcess([], $message, $context);
    }

    public function getSubscribedRoutingKeys(): array
    {
        return [
            self::QUEUE => ['test.routing'],
        ];
    }
}
