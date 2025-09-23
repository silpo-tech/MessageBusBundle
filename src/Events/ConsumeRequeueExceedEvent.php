<?php

declare(strict_types=1);

namespace MessageBusBundle\Events;

use Interop\Queue\Context;
use Interop\Queue\Message;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;
use MessageBusBundle\Exception\RequeueException;

class ConsumeRequeueExceedEvent
{
    public function __construct(
        public readonly RequeueException $exception,
        public readonly Message $message,
        public readonly Context $context,
        public readonly string $processor,
    ) {
    }
}
