<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Service;

use MessageBusBundle\MessageBus;
use MessageBusBundle\Producer\ProducerInterface;
use MessageBusBundle\Service\ProducerService;
use MessageBusBundle\Tests\DataProvider\ProducerDataProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\SerializerInterface;

class ProducerServiceTest extends TestCase
{
    private ProducerInterface|MockObject $producer;
    private SerializerInterface|MockObject $serializer;
    private ProducerService $service;

    protected function setUp(): void
    {
        $this->producer = $this->createMock(ProducerInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->service = new ProducerService($this->producer, $this->serializer);
    }

    #[DataProvider('providerMessageData')]
    public function testSendMessage(array $data): void
    {
        $this->producer->expects($this->once())
            ->method('send')
            ->with(
                $data['topic'],
                $data['message'],
                $data['headers'],
                $data['delay'],
                $data['exchange']
            );

        $result = $this->service->sendMessage(
            $data['topic'],
            $data['message'],
            $data['headers'],
            $data['exchange'],
            $data['delay']
        );

        $this->assertSame($this->service, $result);
    }

    public function testSendDtoWithJsonSerializable(): void
    {
        $dto = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['key' => 'value'];
            }
        };

        $this->producer->expects($this->once())
            ->method('send')
            ->with(
                'test-topic',
                '{"key":"value"}',
                [ProducerService::CLASS_HEADER => get_class($dto)],
                0,
                MessageBus::DEFAULT_EXCHANGE
            );

        $this->serializer->expects($this->never())->method('serialize');

        $result = $this->service->sendDto('test-topic', $dto);

        $this->assertSame($this->service, $result);
    }

    public static function providerMessageData(): iterable
    {
        return ProducerDataProvider::messageData();
    }
}
