<?php

declare(strict_types=1);

namespace MessageBusBundle\Events;

use Interop\Queue\Context;
use Interop\Queue\Message;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;
use MessageBusBundle\Exception\RejectException;

class ConsumeRejectEvent
{
    public function __construct(
        public readonly RejectException $exception,
        public readonly Message $message,
        public readonly Context $context,
        public readonly string $processor,
    ) {
    }
}
