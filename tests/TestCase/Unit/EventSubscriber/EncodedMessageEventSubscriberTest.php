<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EventSubscriber;

use Interop\Queue\Context;
use Interop\Queue\Message;
use MessageBusBundle\Encoder\EncoderInterface;
use MessageBusBundle\Encoder\EncoderRegistry;
use MessageBusBundle\Events\BatchConsumeEvent;
use MessageBusBundle\Events\PreConsumeEvent;
use MessageBusBundle\EventSubscriber\EncodedMessageEventSubscriber;
use MessageBusBundle\MessageBus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EncodedMessageEventSubscriberTest extends TestCase
{
    #[DataProvider('decodeProvider')]
    public function testDecodeMessage(?string $encodingHeader, bool $expectsDecode): void
    {
        $encoderRegistry = $this->createMock(EncoderRegistry::class);
        $encoder = $this->createMock(EncoderInterface::class);
        $message = $this->createMock(Message::class);
        $context = $this->createMock(Context::class);

        $event = new PreConsumeEvent($message, $context, 'TestProcessor');

        $message->method('getProperty')
            ->with(MessageBus::ENCODING_HEADER)
            ->willReturn($encodingHeader);

        if ($expectsDecode) {
            $message->method('getBody')->willReturn('encoded_content');

            $encoderRegistry->expects($this->once())
                ->method('getEncoder')
                ->with($encodingHeader)
                ->willReturn($encoder);

            $encoder->expects($this->once())
                ->method('decode')
                ->with('encoded_content')
                ->willReturn('decoded_content');

            $message->expects($this->once())
                ->method('setBody')
                ->with('decoded_content');

            $message->expects($this->once())
                ->method('setProperty')
                ->with(MessageBus::ENCODING_HEADER, null);
        } else {
            $encoderRegistry->expects($this->never())->method('getEncoder');
            $message->expects($this->never())->method('setBody');
        }

        $subscriber = new EncodedMessageEventSubscriber($encoderRegistry);
        $subscriber->decodeMessage($event);
    }

    #[DataProvider('decodeProvider')]
    public function testDecodeMessages(?string $encodingHeader, bool $expectsDecode): void
    {
        $encoderRegistry = $this->createMock(EncoderRegistry::class);
        $encoder = $this->createMock(EncoderInterface::class);
        $message = $this->createMock(Message::class);
        $context = $this->createMock(Context::class);

        $event = new BatchConsumeEvent([$message], $context, 'TestProcessor');

        $message->method('getProperty')
            ->with(MessageBus::ENCODING_HEADER)
            ->willReturn($encodingHeader);

        if ($expectsDecode) {
            $message->method('getBody')->willReturn('encoded_content');

            $encoderRegistry->expects($this->once())
                ->method('getEncoder')
                ->with($encodingHeader)
                ->willReturn($encoder);

            $encoder->expects($this->once())
                ->method('decode')
                ->with('encoded_content')
                ->willReturn('decoded_content');

            $message->expects($this->once())
                ->method('setBody')
                ->with('decoded_content');

            $message->expects($this->once())
                ->method('setProperty')
                ->with(MessageBus::ENCODING_HEADER, null);
        } else {
            $encoderRegistry->expects($this->never())->method('getEncoder');
            $message->expects($this->never())->method('setBody');
        }

        $subscriber = new EncodedMessageEventSubscriber($encoderRegistry);
        $subscriber->decodeMessages($event);
    }

    public static function decodeProvider(): array
    {
        return [
            'with gzip encoding' => [
                'encodingHeader' => 'gzip',
                'expectsDecode' => true,
            ],
            'with deflate encoding' => [
                'encodingHeader' => 'deflate',
                'expectsDecode' => true,
            ],
            'with zlib encoding' => [
                'encodingHeader' => 'zlib',
                'expectsDecode' => true,
            ],
            'without encoding header' => [
                'encodingHeader' => null,
                'expectsDecode' => false,
            ],
        ];
    }
}
