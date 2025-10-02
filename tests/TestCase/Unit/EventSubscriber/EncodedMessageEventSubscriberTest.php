<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EventSubscriber;

use Interop\Queue\Context;
use Interop\Queue\Message;
use MessageBusBundle\Encoder\EncoderInterface;
use MessageBusBundle\Encoder\EncoderRegistry;
use MessageBusBundle\Events;
use MessageBusBundle\Events\PreConsumeEvent;
use MessageBusBundle\EventSubscriber\EncodedMessageEventSubscriber;
use MessageBusBundle\MessageBus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EncodedMessageEventSubscriberTest extends TestCase
{
    private EncoderRegistry|MockObject $encoderRegistry;
    private EncoderInterface|MockObject $encoder;
    private EncodedMessageEventSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->encoderRegistry = $this->createMock(EncoderRegistry::class);
        $this->encoder = $this->createMock(EncoderInterface::class);
        $this->subscriber = new EncodedMessageEventSubscriber($this->encoderRegistry);
    }

    public function testDecodeMessageWithEncoding(): void
    {
        $message = $this->createMock(Message::class);
        $context = $this->createMock(Context::class);
        $event = new PreConsumeEvent($message, $context, 'TestProcessor');

        $encodedBody = 'encoded_content';
        $decodedBody = 'decoded_content';

        $message->method('getProperty')
            ->with(MessageBus::ENCODING_HEADER)
            ->willReturn('gzip');
        $message->method('getBody')->willReturn($encodedBody);

        $this->encoderRegistry->expects($this->once())
            ->method('getEncoder')
            ->with('gzip')
            ->willReturn($this->encoder);

        $this->encoder->expects($this->once())
            ->method('decode')
            ->with($encodedBody)
            ->willReturn($decodedBody);

        $message->expects($this->once())
            ->method('setBody')
            ->with($decodedBody);

        $message->expects($this->once())
            ->method('setProperty')
            ->with(MessageBus::ENCODING_HEADER, null);

        $this->subscriber->decodeMessage($event);
    }

    public function testDecodeMessageWithoutEncoding(): void
    {
        $message = $this->createMock(Message::class);
        $context = $this->createMock(Context::class);
        $event = new PreConsumeEvent($message, $context, 'TestProcessor');

        $message->method('getProperty')
            ->with(MessageBus::ENCODING_HEADER)
            ->willReturn(null);

        $this->encoderRegistry->expects($this->never())
            ->method('getEncoder');

        $message->expects($this->never())
            ->method('setBody');

        $this->subscriber->decodeMessage($event);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = EncodedMessageEventSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(Events::CONSUME__PRE_START, $events);
        $this->assertEquals('decodeMessage', $events[Events::CONSUME__PRE_START]);
        $this->assertArrayHasKey(Events::BATCH_CONSUME__START, $events);
        $this->assertEquals('decodeMessages', $events[Events::BATCH_CONSUME__START]);
    }
}
