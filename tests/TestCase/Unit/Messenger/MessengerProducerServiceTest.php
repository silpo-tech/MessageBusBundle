<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Messenger;

use MessageBusBundle\MessageBus;
use MessageBusBundle\Messenger\Adapter\MessengerProducerService;
use MessageBusBundle\Messenger\Message\PublishEnqueueMessage;
use MessageBusBundle\Producer\ProducerInterface;
use MessageBusBundle\Service\ProducerService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

class MessengerProducerServiceTest extends TestCase
{
    private ProducerInterface $producer;
    private MessageBusInterface $bus;
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        $this->producer = $this->createMock(ProducerInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
    }

    public function testFlagOffFallsBackToLegacyProducer(): void
    {
        $service = new MessengerProducerService($this->producer, $this->serializer, $this->bus, false);

        // ProducerService::sendMessage() delegates to producer->send(topic, body, headers, delay, exchange).
        $this->producer->expects($this->once())
            ->method('send')
            ->with('the-topic', 'the-body', [], 0, MessageBus::DEFAULT_EXCHANGE);

        $this->bus->expects($this->never())->method('dispatch');

        $service->sendMessage('the-topic', 'the-body');
    }

    public function testFlagOnPublishesThroughMessengerWithTopicAsRoutingKey(): void
    {
        $service = new MessengerProducerService($this->producer, $this->serializer, $this->bus, true);

        $this->producer->expects($this->never())->method('send');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with(
                self::callback(function (PublishEnqueueMessage $message): bool {
                    self::assertSame('the-topic', $message->getTopic());
                    self::assertSame('the-body', $message->getBody());
                    self::assertSame(['h' => 'v'], $message->getProperties());

                    return true;
                }),
                self::callback(function (array $stamps): bool {
                    self::assertInstanceOf(AmqpStamp::class, $stamps[0]);
                    self::assertSame('the-topic', $stamps[0]->getRoutingKey());

                    return true;
                }),
            )
            ->willReturn(new Envelope(new \stdClass()));

        $service->sendMessage('the-topic', 'the-body', ['h' => 'v']);
    }

    public function testSendDtoIsRoutedThroughMessengerWithClassHeaderAndJsonBody(): void
    {
        $service = new MessengerProducerService($this->producer, $this->serializer, $this->bus, true);

        $dto = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['a' => 1];
            }
        };

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(function (PublishEnqueueMessage $message) use ($dto): bool {
                self::assertSame('the-topic', $message->getTopic());
                self::assertSame(json_encode(['a' => 1]), $message->getBody());
                self::assertSame($dto::class, $message->getProperties()[ProducerService::CLASS_HEADER]);

                return true;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $service->sendDto('the-topic', $dto);
    }
}
