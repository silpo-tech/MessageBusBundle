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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RabbitMqQueueManagerTest extends TestCase
{
    private AmqpContext|MockObject $context;
    private RabbitMqQueueManager $manager;

    protected function setUp(): void
    {
        $this->context = $this->createMock(AmqpContext::class);
        $this->manager = new RabbitMqQueueManager($this->context);
    }

    public function testInitQueueWithStringRoutingKeys(): void
    {
        $queue = $this->createMock(AmqpQueue::class);
        $topic = $this->createMock(AmqpTopic::class);

        $this->context->expects($this->once())
            ->method('createQueue')
            ->with('test_queue')
            ->willReturn($queue);

        $queue->expects($this->once())
            ->method('setFlags')
            ->with(\AMQP_DURABLE);

        $this->context->expects($this->once())
            ->method('declareQueue')
            ->with($queue);

        $this->context->expects($this->once())
            ->method('createTopic')
            ->with(MessageBus::DEFAULT_EXCHANGE)
            ->willReturn($topic);

        $topic->expects($this->once())
            ->method('setFlags')
            ->with(\AMQP_DURABLE);

        $this->context->expects($this->once())
            ->method('declareTopic')
            ->with($topic);

        $this->context->expects($this->once())
            ->method('bind')
            ->with($this->isInstanceOf(AmqpBind::class));

        $this->manager->initQueue('test_queue', ['test_routing_key']);
    }

    public function testInitQueueWithArrayRoutingKeys(): void
    {
        $queue = $this->createMock(AmqpQueue::class);
        $topic = $this->createMock(AmqpTopic::class);

        $this->context->expects($this->once())
            ->method('createQueue')
            ->with('test_queue')
            ->willReturn($queue);

        $this->context->expects($this->once())
            ->method('createTopic')
            ->with('custom_exchange')
            ->willReturn($topic);

        $routingKeys = [
            ['routingKey' => 'test_key', 'exchange' => 'custom_exchange'],
        ];

        $this->manager->initQueue('test_queue', $routingKeys);
    }

    public function testInitQueueWithNonAmqpContext(): void
    {
        $context = $this->createMock(Context::class);
        $manager = new RabbitMqQueueManager($context);

        // Should not call any methods on non-AMQP context
        $manager->initQueue('test_queue', ['test_key']);

        $this->expectNotToPerformAssertions();
    }
}
