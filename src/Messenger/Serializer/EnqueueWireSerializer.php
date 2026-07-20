<?php

declare(strict_types=1);

namespace MessageBusBundle\Messenger\Serializer;

use MessageBusBundle\Messenger\Bridge\EnqueueEnvelope;
use MessageBusBundle\Messenger\Message\EnqueueWireMessageInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
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
    /**
     * MessageBusBundle's own retry counter (see {@see \MessageBusBundle\EnqueueProcessor\ExceptionHandler\RequeueHandler}).
     * We reuse this exact AMQP header as the bridge between the two retry worlds: on the wire the count
     * still lives in `requeue-count` (so the legacy producer/consumer and the processor keep reading it),
     * and in Messenger it is mirrored into a {@see RedeliveryStamp} so `retry_strategy` counts attempts.
     */
    private const REQUEUE_COUNT_HEADER = 'requeue-count';

    public function __construct(private ?string $processorId = null)
    {
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

        $stamps = [];
        // Resume Messenger's retry count from the wire so a redelivered message is not treated as a
        // fresh attempt (which would loop forever). The count stays in $properties too, so the
        // processor still reads `requeue-count` off the message exactly as under enqueue.
        $retryCount = (int) ($properties[self::REQUEUE_COUNT_HEADER] ?? 0);
        if ($retryCount > 0) {
            $stamps[] = new RedeliveryStamp($retryCount);
        }

        return new Envelope(new EnqueueEnvelope($this->processorId, $body, $properties), $stamps);
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

        $properties = $message->getProperties();

        // Persist the current attempt number back into the `requeue-count` header so it survives the
        // trip to the broker (a delayed retry copy) and is picked up again by decode() / the processor.
        $retryCount = RedeliveryStamp::getRetryCountFromEnvelope($envelope);
        if ($retryCount > 0) {
            $properties[self::REQUEUE_COUNT_HEADER] = $retryCount;
        }

        return [
            'body' => $message->getBody(),
            'headers' => $properties,
        ];
    }
}
