<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Producer;

use Interop\Queue\Message;
use MessageBusBundle\Encoder\EncoderInterface;
use MessageBusBundle\Producer\EncoderProducer;
use MessageBusBundle\Producer\ProducerInterface;
use MessageBusBundle\Tests\DataProvider\ProducerDataProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EncoderProducerTest extends TestCase
{
    private ProducerInterface|MockObject $producer;
    private EncoderInterface|MockObject $encoder;
    private EncoderProducer $encoderProducer;

    protected function setUp(): void
    {
        $this->producer = $this->createMock(ProducerInterface::class);
        $this->encoder = $this->createMock(EncoderInterface::class);

        $this->encoderProducer = new EncoderProducer($this->producer, $this->encoder);
    }

    #[DataProvider('providerSendData')]
    public function testSend(array $data): void
    {
        $encodedMessage = 'encoded_'.$data['message'];

        $this->encoder->expects($this->once())
            ->method('encode')
            ->with($data['message'])
            ->willReturn($encodedMessage);

        $this->encoder->expects($this->once())
            ->method('getEncoding')
            ->willReturn('gzip');

        $expectedHeaders = array_merge($data['headers'], ['content_encoding' => 'gzip']);

        $this->producer->expects($this->once())
            ->method('send')
            ->with(
                $data['topic'],
                $encodedMessage,
                $expectedHeaders,
                $data['delay'],
                $data['exchange']
            )
            ->willReturn($this->producer);

        $result = $this->encoderProducer->send(
            $data['topic'],
            $data['message'],
            $data['headers'],
            $data['delay'],
            $data['exchange']
        );

        $this->assertSame($this->encoderProducer, $result);
    }

    #[DataProvider('providerQueueData')]
    public function testSendToQueue(array $data): void
    {
        $encodedMessage = 'encoded_'.$data['message'];

        $this->encoder->expects($this->once())
            ->method('encode')
            ->with($data['message'])
            ->willReturn($encodedMessage);

        $this->encoder->expects($this->once())
            ->method('getEncoding')
            ->willReturn('gzip');

        $expectedHeaders = array_merge($data['headers'], ['content_encoding' => 'gzip']);

        $this->producer->expects($this->once())
            ->method('sendToQueue')
            ->with(
                $data['queue'],
                $encodedMessage,
                $expectedHeaders,
                $data['delay']
            )
            ->willReturn($this->producer);

        $result = $this->encoderProducer->sendToQueue(
            $data['queue'],
            $data['message'],
            $data['headers'],
            $data['delay']
        );

        $this->assertSame($this->encoderProducer, $result);
    }

    public function testSendMessage(): void
    {
        $message = $this->createMock(Message::class);
        $originalBody = 'test message body';
        $encodedBody = 'encoded_test message body';

        // Message doesn't have encoding header, so it will be encoded
        $message->method('getHeader')
            ->with('content_encoding')
            ->willReturn(null);

        $message->method('getBody')
            ->willReturn($originalBody);

        $this->encoder->expects($this->once())
            ->method('encode')
            ->with($originalBody)
            ->willReturn($encodedBody);

        $this->encoder->expects($this->once())
            ->method('getEncoding')
            ->willReturn('gzip');

        $message->expects($this->once())
            ->method('setBody')
            ->with($encodedBody);

        $message->expects($this->once())
            ->method('setHeader')
            ->with('content_encoding', 'gzip');

        $this->producer->expects($this->once())
            ->method('sendMessage')
            ->with('test-topic', $message, 0, 'messagebus')
            ->willReturn($this->producer);

        $result = $this->encoderProducer->sendMessage('test-topic', $message);

        $this->assertSame($this->encoderProducer, $result);
    }

    public static function providerSendData(): iterable
    {
        return ProducerDataProvider::sendData();
    }

    public static function providerQueueData(): iterable
    {
        return ProducerDataProvider::queueData();
    }
}
