<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Service;

use MessageBusBundle\MessageBus;
use MessageBusBundle\Producer\ProducerInterface;
use MessageBusBundle\Service\ProducerService;
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

    public function testConstructor(): void
    {
        $service = new ProducerService($this->producer, $this->serializer);
        $this->assertInstanceOf(ProducerService::class, $service);
    }

    public function testSendMessage(): void
    {
        $this->producer->expects($this->once())
            ->method('send')
            ->with('test.topic', 'test message', ['header1' => 'value1'], 0, MessageBus::DEFAULT_EXCHANGE);

        $result = $this->service->sendMessage('test.topic', 'test message', ['header1' => 'value1']);

        $this->assertSame($this->service, $result);
    }

    public function testSendMessageWithAllParameters(): void
    {
        $this->producer->expects($this->once())
            ->method('send')
            ->with('test.topic', 'test message', ['header1' => 'value1'], 5000, 'custom.exchange');

        $result = $this->service->sendMessage(
            'test.topic',
            'test message',
            ['header1' => 'value1'],
            'custom.exchange',
            5000
        );

        $this->assertSame($this->service, $result);
    }

    public function testSendMessageWithEmptyHeaders(): void
    {
        $this->producer->expects($this->once())
            ->method('send')
            ->with('test.topic', 'test message', [], 0, MessageBus::DEFAULT_EXCHANGE);

        $result = $this->service->sendMessage('test.topic', 'test message');

        $this->assertSame($this->service, $result);
    }

    public function testSendMessageWithDelay(): void
    {
        $this->producer->expects($this->once())
            ->method('send')
            ->with('test.topic', 'test message', [], 3000, MessageBus::DEFAULT_EXCHANGE);

        $result = $this->service->sendMessage('test.topic', 'test message', [], MessageBus::DEFAULT_EXCHANGE, 3000);

        $this->assertSame($this->service, $result);
    }

    public function testSendDtoWithJsonSerializable(): void
    {
        $dto = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['id' => 123, 'name' => 'test'];
            }
        };

        $expectedHeaders = [ProducerService::CLASS_HEADER => get_class($dto)];

        $this->producer->expects($this->once())
            ->method('send')
            ->with('test.topic', '{"id":123,"name":"test"}', $expectedHeaders, 0, MessageBus::DEFAULT_EXCHANGE);

        $this->serializer->expects($this->never())->method('serialize');

        $result = $this->service->sendDto('test.topic', $dto);

        $this->assertSame($this->service, $result);
    }

    public function testSendDtoWithJsonSerializableAndHeaders(): void
    {
        $dto = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['data' => 'value'];
            }
        };

        $inputHeaders = ['custom' => 'header'];
        $expectedHeaders = [
            'custom' => 'header',
            ProducerService::CLASS_HEADER => get_class($dto),
        ];

        $this->producer->expects($this->once())
            ->method('send')
            ->with('test.topic', '{"data":"value"}', $expectedHeaders, 0, MessageBus::DEFAULT_EXCHANGE);

        $result = $this->service->sendDto('test.topic', $dto, $inputHeaders);

        $this->assertSame($this->service, $result);
    }

    public function testSendDtoWithJsonSerializableAllParameters(): void
    {
        $dto = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['test' => true];
            }
        };

        $inputHeaders = ['priority' => 'high'];
        $expectedHeaders = [
            'priority' => 'high',
            ProducerService::CLASS_HEADER => get_class($dto),
        ];

        $this->producer->expects($this->once())
            ->method('send')
            ->with('test.topic', '{"test":true}', $expectedHeaders, 2000, 'custom.exchange');

        $result = $this->service->sendDto('test.topic', $dto, $inputHeaders, 'custom.exchange', 2000);

        $this->assertSame($this->service, $result);
    }

    public function testSendDtoWithNonJsonSerializable(): void
    {
        $dto = new class {
            public string $name = 'test';
            public int $id = 456;
        };

        $serializedData = '{"name":"test","id":456}';
        $expectedHeaders = [ProducerService::CLASS_HEADER => get_class($dto)];

        $this->serializer->expects($this->once())
            ->method('serialize')
            ->with($dto, 'json')
            ->willReturn($serializedData);

        $this->producer->expects($this->once())
            ->method('send')
            ->with('test.topic', $serializedData, $expectedHeaders, 0, MessageBus::DEFAULT_EXCHANGE);

        $result = $this->service->sendDto('test.topic', $dto);

        $this->assertSame($this->service, $result);
    }

    public function testSendDtoWithNonJsonSerializableAndHeaders(): void
    {
        $dto = new class {
            public string $value = 'data';
        };

        $serializedData = '{"value":"data"}';
        $inputHeaders = ['type' => 'object'];
        $expectedHeaders = [
            'type' => 'object',
            ProducerService::CLASS_HEADER => get_class($dto),
        ];

        $this->serializer->expects($this->once())
            ->method('serialize')
            ->with($dto, 'json')
            ->willReturn($serializedData);

        $this->producer->expects($this->once())
            ->method('send')
            ->with('test.topic', $serializedData, $expectedHeaders, 0, MessageBus::DEFAULT_EXCHANGE);

        $result = $this->service->sendDto('test.topic', $dto, $inputHeaders);

        $this->assertSame($this->service, $result);
    }

    public function testSendDtoWithNonJsonSerializableAllParameters(): void
    {
        $dto = new class {
            public array $items = ['a', 'b', 'c'];
        };

        $serializedData = '{"items":["a","b","c"]}';
        $inputHeaders = ['batch' => 'true'];
        $expectedHeaders = [
            'batch' => 'true',
            ProducerService::CLASS_HEADER => get_class($dto),
        ];

        $this->serializer->expects($this->once())
            ->method('serialize')
            ->with($dto, 'json')
            ->willReturn($serializedData);

        $this->producer->expects($this->once())
            ->method('send')
            ->with('test.topic', $serializedData, $expectedHeaders, 1500, 'batch.exchange');

        $result = $this->service->sendDto('test.topic', $dto, $inputHeaders, 'batch.exchange', 1500);

        $this->assertSame($this->service, $result);
    }

    public function testSendDtoOverwritesClassHeader(): void
    {
        $dto = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['overwrite' => 'test'];
            }
        };

        $inputHeaders = [ProducerService::CLASS_HEADER => 'SomeOtherClass'];
        $expectedHeaders = [ProducerService::CLASS_HEADER => get_class($dto)];

        $this->producer->expects($this->once())
            ->method('send')
            ->with('test.topic', '{"overwrite":"test"}', $expectedHeaders, 0, MessageBus::DEFAULT_EXCHANGE);

        $result = $this->service->sendDto('test.topic', $dto, $inputHeaders);

        $this->assertSame($this->service, $result);
    }

    public function testSendDtoWithEmptyJsonSerializable(): void
    {
        $dto = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return [];
            }
        };

        $expectedHeaders = [ProducerService::CLASS_HEADER => get_class($dto)];

        $this->producer->expects($this->once())
            ->method('send')
            ->with('test.topic', '[]', $expectedHeaders, 0, MessageBus::DEFAULT_EXCHANGE);

        $result = $this->service->sendDto('test.topic', $dto);

        $this->assertSame($this->service, $result);
    }

    public function testSendDtoWithNullJsonSerializable(): void
    {
        $dto = new class implements \JsonSerializable {
            public function jsonSerialize(): mixed
            {
                return null;
            }
        };

        $expectedHeaders = [ProducerService::CLASS_HEADER => get_class($dto)];

        $this->producer->expects($this->once())
            ->method('send')
            ->with('test.topic', 'null', $expectedHeaders, 0, MessageBus::DEFAULT_EXCHANGE);

        $result = $this->service->sendDto('test.topic', $dto);

        $this->assertSame($this->service, $result);
    }

    public function testClassHeaderConstant(): void
    {
        $this->assertEquals('php.class_name', ProducerService::CLASS_HEADER);
    }

    public function testChainedCalls(): void
    {
        $this->producer->expects($this->exactly(2))->method('send');

        $result = $this->service
            ->sendMessage('topic1', 'message1')
            ->sendMessage('topic2', 'message2');

        $this->assertSame($this->service, $result);
    }

    public function testChainedCallsWithDto(): void
    {
        $dto = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['chained' => true];
            }
        };

        $this->producer->expects($this->exactly(2))->method('send');

        $result = $this->service
            ->sendMessage('topic1', 'message1')
            ->sendDto('topic2', $dto);

        $this->assertSame($this->service, $result);
    }
}
