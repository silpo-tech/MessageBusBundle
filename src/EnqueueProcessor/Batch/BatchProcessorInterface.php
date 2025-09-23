<?php

declare(strict_types=1);

namespace MessageBusBundle\EnqueueProcessor\Batch;

use Interop\Amqp\AmqpMessage;
use Interop\Queue\Context;
use MessageBusBundle\Exception\InterruptProcessingException;

interface BatchProcessorInterface
{
    public const ACK = 'enqueue.ack';
    public const REJECT = 'enqueue.reject';
    public const REQUEUE = 'enqueue.requeue';

    /**
     * @param AmqpMessage[] $messagesBatch
     *
     * @return string[]
     *
     * @throws InterruptProcessingException
     */
    public function process(array $messagesBatch, Context $context): array;
}
