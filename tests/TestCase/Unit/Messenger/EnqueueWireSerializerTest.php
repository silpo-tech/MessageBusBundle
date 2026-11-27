<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Messenger;

use MessageBusBundle\Messenger\Bridge\EnqueueEnvelope;
use MessageBusBundle\Messenger\Serializer\EnqueueWireSerializer;
use MessageBusBundle\Service\ProducerService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

class EnqueueWireSerializerTest extends TestCase
{
    private const PROCESSOR_ID = 'test.processor.send_push';

    private EnqueueWireSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new EnqueueWireSerializer(self::PROCESSOR_ID);
    }

    public function testDecodePreservesBodyAndProperties(): void
    {
        $body = '{"foo":"bar"}';
        $properties = [
            ProducerService::CLASS_HEADER => 'App\DTO\Processor\SendPushNotificationDTO',
            'requeue-count' => 2,
        ];

        $envelope = $this->serializer->decode(['body' => $body, 'headers' => $properties]);

        self::assertInstanceOf(Envelope::class, $envelope);
        $message = $envelope->getMessage();
        self::assertInstanceOf(EnqueueEnvelope::class, $message);
        self::assertSame(self::PROCESSOR_ID, $message->getProcessorId());
        self::assertSame($body, $message->getBody());
        self::assertSame($properties, $message->getProperties());
    }

    public function testDecodeToleratesMissingHeaders(): void
    {
        $envelope = $this->serializer->decode(['body' => '{}']);

        $message = $envelope->getMessage();
        self::assertInstanceOf(EnqueueEnvelope::class, $message);
        self::assertSame('{}', $message->getBody());
        self::assertSame([], $message->getProperties());
    }

    public function testEncodeMirrorsDecodeWireFormat(): void
    {
        $properties = [ProducerService::CLASS_HEADER => 'App\DTO\Processor\SendPushNotificationDTO'];
        $message = new EnqueueEnvelope(self::PROCESSOR_ID, '{"foo":"bar"}', $properties);

        $encoded = $this->serializer->encode(new Envelope($message));

        self::assertSame(['body' => '{"foo":"bar"}', 'headers' => $properties], $encoded);
    }

    public function testEncodeRejectsForeignMessage(): void
    {
        $this->expectException(\LogicException::class);

        $this->serializer->encode(new Envelope(new \stdClass()));
    }

    public function testDecodeResumesRetryCountFromRequeueHeader(): void
    {
        $envelope = $this->serializer->decode(['body' => '{}', 'headers' => ['requeue-count' => 3]]);

        self::assertSame(3, RedeliveryStamp::getRetryCountFromEnvelope($envelope));
        // The header also stays on the message so the processor reads `requeue-count` as before.
        self::assertSame(3, $envelope->getMessage()->getProperties()['requeue-count']);
    }

    public function testDecodeAddsNoRedeliveryStampForAFreshMessage(): void
    {
        $envelope = $this->serializer->decode(['body' => '{}']);

        self::assertNull($envelope->last(RedeliveryStamp::class));
        self::assertSame(0, RedeliveryStamp::getRetryCountFromEnvelope($envelope));
    }

    public function testEncodeWritesRetryCountBackIntoRequeueHeader(): void
    {
        $message = new EnqueueEnvelope(self::PROCESSOR_ID, '{}');
        $envelope = new Envelope($message, [new RedeliveryStamp(2)]);

        $encoded = $this->serializer->encode($envelope);

        self::assertSame(2, $encoded['headers']['requeue-count']);
    }

    public function testRetryCountRoundTripsThroughTheWire(): void
    {
        // A redelivered message re-encoded then decoded must keep the same attempt number, so a
        // self-capping processor sees the real count and Messenger does not restart at zero.
        $encoded = $this->serializer->encode(
            new Envelope(new EnqueueEnvelope(self::PROCESSOR_ID, '{}'), [new RedeliveryStamp(4)]),
        );

        $decoded = $this->serializer->decode($encoded);

        self::assertSame(4, RedeliveryStamp::getRetryCountFromEnvelope($decoded));
    }
}
