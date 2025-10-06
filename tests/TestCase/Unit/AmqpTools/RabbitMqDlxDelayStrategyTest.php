<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\AmqpTools;

use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpProducer;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\Impl\AmqpMessage as AmqpMessageImpl;
use Interop\Amqp\Impl\AmqpQueue as AmqpQueueImpl;
use Interop\Amqp\Impl\AmqpTopic as AmqpTopicImpl;
use Interop\Queue\Exception\InvalidDestinationException;
use MessageBusBundle\AmqpTools\RabbitMqDlxDelayStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RabbitMqDlxDelayStrategyTest extends TestCase
{
    #[DataProvider('delayMessageProvider')]
    public function testDelayMessage(
        AmqpDestination $destination,
        array $messageProperties,
        array $expectedProperties,
        int $delay,
        string $expectedQueueName
    ): void {
        $strategy = new RabbitMqDlxDelayStrategy();
        $message = new AmqpMessageImpl('test body', $messageProperties, ['header' => 'value']);
        $message->setRoutingKey('test.routing');

        $context = $this->createMock(AmqpContext::class);
        $producer = $this->createMock(AmqpProducer::class);

        $context->method('declareQueue');
        $context->method('createProducer')->willReturn($producer);
        $context->method('createMessage')->willReturnCallback(
            fn ($body, $properties, $headers) => new AmqpMessageImpl($body, $properties, $headers)
        );
        $context->method('createQueue')->willReturnCallback(fn ($name) => new AmqpQueueImpl($name));

        $producer->expects($this->once())
            ->method('send')
            ->with(
                $this->callback(function (AmqpQueue $queue) use ($expectedQueueName) {
                    $this->assertSame($expectedQueueName, $queue->getQueueName());

                    return true;
                }),
                $this->callback(function (AmqpMessage $message) use ($expectedProperties) {
                    $this->assertSame('test body', $message->getBody());
                    $this->assertEquals($expectedProperties, $message->getProperties());
                    $this->assertEquals(['header' => 'value'], $message->getHeaders());
                    $this->assertSame('test.routing', $message->getRoutingKey());

                    return true;
                })
            );

        $strategy->delayMessage($context, $destination, $message, $delay);
    }

    public function testDelayMessageWithInvalidDestination(): void
    {
        $strategy = new RabbitMqDlxDelayStrategy();
        $message = new AmqpMessageImpl('test body');
        $message->setRoutingKey('test.routing');
        $context = $this->createMock(AmqpContext::class);

        // Mock context to return a proper message with setRoutingKey method
        $context->method('createMessage')->willReturnCallback(
            fn ($body, $properties, $headers) => new AmqpMessageImpl($body, $properties, $headers)
        );

        // Create an invalid destination (not AmqpTopic or AmqpQueue)
        $invalidDestination = $this->createMock(AmqpDestination::class);

        $this->expectException(InvalidDestinationException::class);
        $this->expectExceptionMessage('The destination must be an instance of');

        $strategy->delayMessage($context, $invalidDestination, $message, 1000);
    }

    public static function delayMessageProvider(): array
    {
        return [
            'topic' => [
                'destination' => new AmqpTopicImpl('test.topic'),
                'messageProperties' => ['prop' => 'value'],
                'expectedProperties' => ['prop' => 'value'],
                'delay' => 5000,
                'expectedQueueName' => 'test.routing.5000.x.delay',
            ],
            'queue' => [
                'destination' => new AmqpQueueImpl('test.queue'),
                'messageProperties' => ['prop' => 'value'],
                'expectedProperties' => ['prop' => 'value'],
                'delay' => 3000,
                'expectedQueueName' => 'test.queue.3000.delayed',
            ],
            'x-death header removal' => [
                'destination' => new AmqpTopicImpl('test.topic'),
                'messageProperties' => ['x-death' => 'should be removed', 'other' => 'kept'],
                'expectedProperties' => ['other' => 'kept'],
                'delay' => 1000,
                'expectedQueueName' => 'test.routing.1000.x.delay',
            ],
        ];
    }
}
