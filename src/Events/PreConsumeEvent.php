<?php

declare(strict_types=1);

namespace MessageBusBundle\Events;

use Interop\Queue\Context;
use Interop\Queue\Message;

class PreConsumeEvent
{
    private Message $message;
    private Context $context;
    private string $processorClass;

    public function __construct(Message $message, Context $context, string $processorClass)
    {
        $this->message = $message;
        $this->context = $context;
        $this->processorClass = $processorClass;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getProcessorClass(): string
    {
        return $this->processorClass;
    }
}
