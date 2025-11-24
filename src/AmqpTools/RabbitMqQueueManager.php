<?php

declare(strict_types=1);

namespace MessageBusBundle\AmqpTools;

use Interop\Amqp\AmqpContext;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Queue\Context;
use MessageBusBundle\MessageBus;

class RabbitMqQueueManager
{
    public function __construct(private readonly Context $context)
    {
    }

    public function initQueue(string $queueName, array $routingKeys, QueueType $queueType = QueueType::DEFAULT): void
    {
        if ($this->context instanceof AmqpContext) {
            $queue = $this->context->createQueue($queueName);
            $queue->setFlags(AMQP_DURABLE);

            if (QueueType::QUORUM === $queueType) {
                $queue->setArguments([
                    'x-queue-type' => 'quorum',
                ]);
            }

            $this->context->declareQueue($queue);

            foreach ($routingKeys as $routingKey) {
                if (is_array($routingKey)) {
                    $routingKeyName = $routingKey['routingKey'];
                    $exchangeName = $routingKey['exchange'];
                } else {
                    $routingKeyName = $routingKey;
                    $exchangeName = MessageBus::DEFAULT_EXCHANGE;
                }
                $exchange = $this->context->createTopic($exchangeName);
                $exchange->setFlags(AMQP_DURABLE);
                $this->context->declareTopic($exchange);
                $this->context->bind(new AmqpBind($exchange, $queue, $routingKeyName));
            }
        }
    }
}
