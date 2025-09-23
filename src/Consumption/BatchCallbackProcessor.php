<?php

namespace MessageBusBundle\Consumption;

use Interop\Queue\Context;
use Interop\Queue\Message;
use MessageBusBundle\EnqueueProcessor\Batch\BatchProcessorInterface;

class BatchCallbackProcessor implements BatchProcessorInterface
{

    /**
     * @var callable
     */
    private $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * @param Message[] $messagesBatch
     * @return string[]
     */
    public function process(array $messagesBatch, Context $context): array
    {
        return call_user_func($this->callback, $messagesBatch, $context);
    }
}
