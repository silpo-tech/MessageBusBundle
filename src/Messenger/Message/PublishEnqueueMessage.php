<?php

declare(strict_types=1);

namespace MessageBusBundle\Messenger\Message;

use MessageBusBundle\MessageBus;

/**
 * Outbound counterpart of {@see \MessageBusBundle\Messenger\Bridge\EnqueueEnvelope}: a message
 * published through Symfony Messenger by {@see \MessageBusBundle\Messenger\Adapter\MessengerProducerService}.
 *
 * It carries the topic (AMQP routing key) alongside the raw enqueue payload, so the transport
 * serializer emits exactly the wire format the legacy `ProducerService` would have produced —
 * keeping legacy enqueue consumers and the Messenger bridge fully interoperable.
 */
final readonly class PublishEnqueueMessage implements EnqueueWireMessageInterface
{
    /**
     * @param array<string, mixed> $properties AMQP application headers (incl. `php.class_name`)
     */
    public function __construct(
        private string $topic,
        private string $body,
        private array $properties = [],
        private string $exchange = MessageBus::DEFAULT_EXCHANGE,
        private int $delay = 0,
    ) {
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getExchange(): string
    {
        return $this->exchange;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }
}
