<?php

declare(strict_types=1);

namespace MessageBusBundle\Events;

use Interop\Queue\Context;
use Interop\Queue\Message;

class ExceptionConsumeEvent extends ConsumeEvent
{
    private \Throwable $ex;

    public function __construct(\Throwable $ex, $body, Message $message, Context $context, string $processorClass)
    {
        $this->ex = $ex;
        parent::__construct($body, $message, $context, $processorClass);
    }

    public function getException(): \Throwable
    {
        return $this->ex;
    }
}
