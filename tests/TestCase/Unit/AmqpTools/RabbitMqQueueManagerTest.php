<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\AmqpTools;

use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Queue\Context;
use MessageBusBundle\AmqpTools\RabbitMqQueueManager;
use MessageBusBundle\MessageBus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RabbitMqQueueManagerTest extends TestCase
{
    #[DataProvider('initQueueProvider')]
    public function testInitQueue(array $routingKeys, array $expectedExchanges, array $expectedRoutingKeys): void
    {
        $context = $this->createMock(AmqpContext::class);
        $queue = $this->createMock(AmqpQueue::class);
        $topic = $this->createMock(AmqpTopic::class);
        $manager = new RabbitMqQueueManager($context);

        $context->expects($this->once())
            ->method('createQueue')
            ->with('test_queue')
            ->willReturn($queue);

        $topicCallIndex = 0;
        $context
            ->expects($this->exactly(count($expectedExchanges)))
            ->method('createTopic')
            ->willReturnCallback(function ($exchangeName) use ($expectedExchanges, $topic, &$topicCallIndex) {
                $this->assertSame($expectedExchanges[$topicCallIndex], $exchangeName);
                ++$topicCallIndex;

                return $topic;
            });

        $bindCallIndex = 0;
        $context
            ->expects($this->exactly(count($expectedRoutingKeys)))
            ->method('bind')
            ->willReturnCallback(function (AmqpBind $bind) use ($expectedRoutingKeys, &$bindCallIndex) {
                $this->assertSame($expectedRoutingKeys[$bindCallIndex], $bind->getRoutingKey());
                ++$bindCallIndex;
            });

        $manager->initQueue('test_queue', $routingKeys);
    }

    public function testInitQueueWithNonAmqpContext(): void
    {
        $context = $this->createMock(Context::class);
        $context->expects($this->never())->method('createQueue');

        $manager = new RabbitMqQueueManager($context);
        $manager->initQueue('test_queue', ['test_key']);
    }

    public static function initQueueProvider(): array
    {
        return [
            'string routing keys' => [
                'routingKeys' => ['test_routing_key'],
                'expectedExchanges' => [MessageBus::DEFAULT_EXCHANGE],
                'expectedRoutingKeys' => ['test_routing_key'],
            ],
            'multiple string routing keys' => [
                'routingKeys' => ['key1', 'key2', 'key3'],
                'expectedExchanges' => [MessageBus::DEFAULT_EXCHANGE, MessageBus::DEFAULT_EXCHANGE, MessageBus::DEFAULT_EXCHANGE],
                'expectedRoutingKeys' => ['key1', 'key2', 'key3'],
            ],
            'array routing keys' => [
                'routingKeys' => [['routingKey' => 'test_key', 'exchange' => 'custom_exchange']],
                'expectedExchanges' => ['custom_exchange'],
                'expectedRoutingKeys' => ['test_key'],
            ],
            'mixed routing keys' => [
                'routingKeys' => [
                    'simple_key',
                    ['routingKey' => 'complex_key', 'exchange' => 'custom_exchange'],
                ],
                'expectedExchanges' => [MessageBus::DEFAULT_EXCHANGE, 'custom_exchange'],
                'expectedRoutingKeys' => ['simple_key', 'complex_key'],
            ],
        ];
    }
}
