<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\AmqpTools;

use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use MessageBusBundle\AmqpTools\QueueType;
use MessageBusBundle\AmqpTools\RabbitMqQueueManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RabbitMqQueueManagerTest extends TestCase
{
    private AmqpContext|MockObject $context;
    private RabbitMqQueueManager $queueManager;

    protected function setUp(): void
    {
        $this->context = $this->createMock(AmqpContext::class);
        $this->queueManager = new RabbitMqQueueManager($this->context);
    }

    public function testInitQueueWithDefaultType(): void
    {
        $queueName = 'test.queue';
        $routingKeys = ['test.routing.key'];

        $queue = $this->createMock(AmqpQueue::class);
        $topic = $this->createMock(AmqpTopic::class);

        $this->context->expects($this->once())
            ->method('createQueue')
            ->with($queueName)
            ->willReturn($queue);

        $queue->expects($this->once())
            ->method('setFlags')
            ->with(AMQP_DURABLE);

        $queue->expects($this->never())
            ->method('setArguments');

        $this->context->expects($this->once())
            ->method('declareQueue')
            ->with($queue);

        $this->context->expects($this->once())
            ->method('createTopic')
            ->willReturn($topic);

        $topic->expects($this->once())
            ->method('setFlags')
            ->with(AMQP_DURABLE);

        $this->context->expects($this->once())
            ->method('declareTopic')
            ->with($topic);

        $this->context->expects($this->once())
            ->method('bind')
            ->with($this->isInstanceOf(AmqpBind::class));

        $this->queueManager->initQueue($queueName, $routingKeys, QueueType::DEFAULT);
    }

    public function testInitQueueWithQuorumType(): void
    {
        $queueName = 'test.quorum.queue';
        $routingKeys = ['test.routing.key'];

        $queue = $this->createMock(AmqpQueue::class);
        $topic = $this->createMock(AmqpTopic::class);

        $this->context->expects($this->once())
            ->method('createQueue')
            ->with($queueName)
            ->willReturn($queue);

        $queue->expects($this->once())
            ->method('setFlags')
            ->with(AMQP_DURABLE);

        $queue->expects($this->once())
            ->method('setArguments')
            ->with(['x-queue-type' => 'quorum']);

        $this->context->expects($this->once())
            ->method('declareQueue')
            ->with($queue);

        $this->context->expects($this->once())
            ->method('createTopic')
            ->willReturn($topic);

        $topic->expects($this->once())
            ->method('setFlags')
            ->with(AMQP_DURABLE);

        $this->context->expects($this->once())
            ->method('declareTopic')
            ->with($topic);

        $this->context->expects($this->once())
            ->method('bind')
            ->with($this->isInstanceOf(AmqpBind::class));

        $this->queueManager->initQueue($queueName, $routingKeys, QueueType::QUORUM);
    }

    public function testInitQueueWithDefaultTypeWhenNotSpecified(): void
    {
        $queueName = 'test.queue';
        $routingKeys = ['test.routing.key'];

        $queue = $this->createMock(AmqpQueue::class);
        $topic = $this->createMock(AmqpTopic::class);

        $this->context->expects($this->once())
            ->method('createQueue')
            ->with($queueName)
            ->willReturn($queue);

        $queue->expects($this->once())
            ->method('setFlags')
            ->with(AMQP_DURABLE);

        $queue->expects($this->never())
            ->method('setArguments');

        $this->context->expects($this->once())
            ->method('declareQueue')
            ->with($queue);

        $this->context->expects($this->once())
            ->method('createTopic')
            ->willReturn($topic);

        $this->context->expects($this->once())
            ->method('declareTopic')
            ->with($topic);

        $this->context->expects($this->once())
            ->method('bind');

        // Call without specifying queue type - should default to DEFAULT
        $this->queueManager->initQueue($queueName, $routingKeys);
    }
}
