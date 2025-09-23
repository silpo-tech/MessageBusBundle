<?php

namespace MessageBusBundle\EnqueueProcessor;

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
     *
     * @return array
     */
    public function getSubscribedRoutingKeys(): array;
}
