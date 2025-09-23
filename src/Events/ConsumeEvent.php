<?php

declare(strict_types=1);

namespace MessageBusBundle\Events;

use Interop\Queue\Context;
use Interop\Queue\Message;

class ConsumeEvent
{
    /**
     * @var array|object
     */
    private $body;
    private Message $message;
    private Context $context;
    private string $processorClass;

    public function __construct($body, Message $message, Context $context, string $processorClass)
    {
        $this->body = $body;
        $this->message = $message;
        $this->context = $context;
        $this->processorClass = $processorClass;
    }

    /**
     * @return array|object
     */
    public function getBody()
    {
        return $this->body;
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
