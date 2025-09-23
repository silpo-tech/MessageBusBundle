<?php

declare(strict_types=1);

namespace MessageBusBundle\AmqpTools;

use Enqueue\AmqpTools\DelayStrategy;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Queue\Exception\InvalidDestinationException;

class RabbitMqDlxDelayStrategy implements DelayStrategy
{
    private const EXPIRES = 60000;

    public function delayMessage(AmqpContext $context, AmqpDestination $dest, AmqpMessage $message, int $delay): void
    {
        $properties = $message->getProperties();

        // The x-death header must be removed because of the bug in RabbitMQ.
        // It was reported that the bug is fixed since 3.5.4 but I tried with 3.6.1 and the bug still there.
        // https://github.com/rabbitmq/rabbitmq-server/issues/216
        unset($properties['x-death']);

        $delayMessage = $context->createMessage($message->getBody(), $properties, $message->getHeaders());
        $delayMessage->setRoutingKey($message->getRoutingKey());

        if ($dest instanceof AmqpTopic) {
            $name = sprintf('%s.%s.x.delay', $message->getRoutingKey(), $delay);
            $delayQueue = $context->createQueue($name);
            $delayQueue->addFlag(AmqpQueue::FLAG_DURABLE);
            $delayQueue->setArgument('x-message-ttl', $delay);
            $delayQueue->setArgument('x-dead-letter-exchange', $dest->getTopicName());
            $delayQueue->setArgument('x-expires', $delay + self::EXPIRES);
            $delayQueue->setArgument('x-dead-letter-routing-key', (string) $delayMessage->getRoutingKey());
        } elseif ($dest instanceof AmqpQueue) {
            $delayQueue = $context->createQueue($dest->getQueueName().'.'.$delay.'.delayed');
            $delayQueue->addFlag(AmqpQueue::FLAG_DURABLE);
            $delayQueue->setArgument('x-message-ttl', $delay);
            $delayQueue->setArgument('x-expires', $delay + self::EXPIRES);
            $delayQueue->setArgument('x-dead-letter-exchange', '');
            $delayQueue->setArgument('x-dead-letter-routing-key', $dest->getQueueName());
        } else {
            throw new InvalidDestinationException(sprintf('The destination must be an instance of %s but got %s.', AmqpTopic::class.'|'.AmqpQueue::class, get_class($dest)));
        }

        $context->declareQueue($delayQueue);

        $context->createProducer()->send($delayQueue, $delayMessage);
    }
}
