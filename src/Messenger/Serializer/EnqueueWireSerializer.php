<?php

declare(strict_types=1);

namespace MessageBusBundle\Messenger\Serializer;

use MessageBusBundle\Messenger\Bridge\EnqueueEnvelope;
use MessageBusBundle\Messenger\Message\EnqueueWireMessageInterface;
use MessengerBundle\Retry\RequeueCountStamper;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Transport serializer that speaks the MessageBusBundle (enqueue) wire format instead of
 * Symfony Messenger's native one. This is what keeps the migration backward compatible:
 * messages published by the legacy `ProducerService` are consumable by `messenger:consume`
 * and vice-versa, because both sides read/write the same body + `php.class_name` header.
 *
 * One instance is bound per transport (per queue), carrying the id of the processor that
 * queue's messages must be routed to — the AMQP routing key is not exposed to `decode()`,
 * and a single worker consumes a single queue, so the processor is fixed per transport.
 *
 * A send-only transport (outbound publishing) needs no processor, so `$processorId` is optional
 * there and only `encode()` is used.
 */
final readonly class EnqueueWireSerializer implements SerializerInterface
{
    private RequeueCountStamper $retryCount;

    public function __construct(private ?string $processorId = null)
    {
        // Bridge the two retry worlds through the bundle's own `requeue-count` header: the count stays
        // on the wire (legacy producer/consumer + the processor keep reading it) and is mirrored into a
        // RedeliveryStamp so Messenger's retry_strategy counts attempts. Mechanics live in MessengerBundle.
        $this->retryCount = new RequeueCountStamper('requeue-count');
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        if (null === $this->processorId) {
            throw new \LogicException(sprintf(
                '%s was configured without a processor id and cannot decode inbound messages; '
                .'it is a send-only serializer.',
                self::class,
            ));
        }

        $body = $encodedEnvelope['body'] ?? '';
        /** @var array<string, mixed> $properties */
        $properties = $encodedEnvelope['headers'] ?? [];

        return new Envelope(
            new EnqueueEnvelope($this->processorId, $body, $properties),
            $this->retryCount->decode($properties),
        );
    }

    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();

        if (!$message instanceof EnqueueWireMessageInterface) {
            throw new \LogicException(sprintf(
                '%s can only encode %s messages, got %s.',
                self::class,
                EnqueueWireMessageInterface::class,
                get_debug_type($message),
            ));
        }

        return [
            'body' => $message->getBody(),
            'headers' => $this->retryCount->encode($envelope, $message->getProperties()),
        ];
    }
}
