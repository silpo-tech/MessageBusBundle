<?php

declare(strict_types=1);

namespace MessageBusBundle\EnqueueProcessor;

use MessageBusBundle\AmqpTools\QueueType;

interface ProcessorInterface
{
    /**
     * The result maybe either:.
     *
     * 'queuename' => [
     *    ['routingKey1', 'routingKey2'],
     * ]
     *
     * 'queuename' => [
     *    ['routingKey'=>'routingKey1', 'exchange' => 'exchangeName'],
     * ]
     */
    public function getSubscribedRoutingKeys(): array;

    public function getQueueType(): QueueType;
}
