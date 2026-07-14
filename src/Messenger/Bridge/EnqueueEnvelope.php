<?php

declare(strict_types=1);

namespace MessageBusBundle\Messenger\Bridge;

use MessageBusBundle\Messenger\Message\EnqueueWireMessageInterface;

/**
 * Carrier message that flows through Symfony Messenger while preserving the raw
 * MessageBusBundle (enqueue) wire payload.
 *
 * Produced by {@see \MessageBusBundle\Messenger\Serializer\EnqueueWireSerializer} on decode
 * and consumed by {@see EnqueueProcessorBridgeHandler}, which replays it into the existing
 * enqueue processor via its native {@see \Interop\Queue\Processor::process()}.
 */
final readonly class EnqueueEnvelope implements EnqueueWireMessageInterface
{
    /**
     * @param string               $processorId service id of the enqueue processor that must handle this message
     * @param string               $body        raw (JSON) message body, exactly as published by the bundle
     * @param array<string, mixed> $properties  AMQP application headers (incl. `php.class_name`, `requeue-count`)
     * @param array<string, mixed> $headers     AMQP native headers
     */
    public function __construct(
        private string $processorId,
        private string $body,
        private array $properties = [],
        private array $headers = [],
    ) {
    }

    public function getProcessorId(): string
    {
        return $this->processorId;
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

    /**
     * @return array<string, mixed>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
