<?php

namespace MessageBusBundle\Events;

use Interop\Queue\Context;
use Interop\Queue\Message;

class BatchExceptionConsumeEvent extends BatchConsumeEvent
{
    private \Throwable $exception;

    /**
     * @param Message[] $messagesBatch
     */
    public function __construct(\Throwable $exception, array $messagesBatch, Context $context, string $processorClass)
    {
        $this->exception = $exception;

        parent::__construct($messagesBatch, $context, $processorClass);
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }
}
