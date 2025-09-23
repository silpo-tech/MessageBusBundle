<?php

namespace MessageBusBundle\Events;

use Interop\Queue\Context;
use Interop\Queue\Message;

class BatchConsumeEvent
{
    /**
     * @var Message[]
     */
    private array $messagesBatch;

    private Context $context;

    private string $processorClass;

    public function __construct(array $messagesBatch, Context $context, string $processorClass)
    {
        $this->messagesBatch = $messagesBatch;
        $this->context = $context;
        $this->processorClass = $processorClass;
    }

    public function getMessagesBatch(): array
    {
        return $this->messagesBatch;
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
