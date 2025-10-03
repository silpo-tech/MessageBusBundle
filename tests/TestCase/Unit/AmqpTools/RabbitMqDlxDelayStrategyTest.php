<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\AmqpTools;

use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpProducer;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use MessageBusBundle\AmqpTools\RabbitMqDlxDelayStrategy;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RabbitMqDlxDelayStrategyTest extends TestCase
{
    private AmqpContext|MockObject $context;
    private AmqpMessage|MockObject $message;
    private AmqpProducer|MockObject $producer;
    private RabbitMqDlxDelayStrategy $strategy;

    protected function setUp(): void
    {
        $this->context = $this->createMock(AmqpContext::class);
        $this->message = $this->createMock(AmqpMessage::class);
        $this->producer = $this->createMock(AmqpProducer::class);
        $this->strategy = new RabbitMqDlxDelayStrategy();
    }

    public function testDelayMessageWithTopic(): void
    {
        $topic = $this->createMock(AmqpTopic::class);
        $delayQueue = $this->createMock(AmqpQueue::class);
        $delayMessage = $this->createMock(AmqpMessage::class);

        $topic->method('getTopicName')->willReturn('test.topic');

        $this->message->method('getBody')->willReturn('test body');
        $this->message->method('getProperties')->willReturn(['prop' => 'value']);
        $this->message->method('getHeaders')->willReturn(['header' => 'value']);
        $this->message->method('getRoutingKey')->willReturn('test.routing');

        $this->context->expects($this->once())
            ->method('createMessage')
            ->with('test body', ['prop' => 'value'], ['header' => 'value'])
            ->willReturn($delayMessage);

        $this->context->expects($this->once())
            ->method('createQueue')
            ->with('test.routing.5000.x.delay')
            ->willReturn($delayQueue);

        $delayQueue->expects($this->once())
            ->method('addFlag')
            ->with(AmqpQueue::FLAG_DURABLE);

        $this->context->expects($this->once())
            ->method('declareQueue')
            ->with($delayQueue);

        $this->context->expects($this->once())
            ->method('createProducer')
            ->willReturn($this->producer);

        $this->producer->expects($this->once())
            ->method('send')
            ->with($delayQueue, $delayMessage);

        $this->strategy->delayMessage($this->context, $topic, $this->message, 5000);
    }

    public function testDelayMessageWithQueue(): void
    {
        $queue = $this->createMock(AmqpQueue::class);
        $delayQueue = $this->createMock(AmqpQueue::class);
        $delayMessage = $this->createMock(AmqpMessage::class);

        $queue->method('getQueueName')->willReturn('test.queue');

        $this->message->method('getBody')->willReturn('test body');
        $this->message->method('getProperties')->willReturn([]);
        $this->message->method('getHeaders')->willReturn([]);
        $this->message->method('getRoutingKey')->willReturn('test.routing');

        $this->context->expects($this->once())
            ->method('createMessage')
            ->willReturn($delayMessage);

        $this->context->expects($this->once())
            ->method('createQueue')
            ->with('test.queue.3000.delayed')
            ->willReturn($delayQueue);

        $delayQueue->expects($this->once())
            ->method('addFlag')
            ->with(AmqpQueue::FLAG_DURABLE);

        $this->context->expects($this->once())
            ->method('declareQueue')
            ->with($delayQueue);

        $this->context->expects($this->once())
            ->method('createProducer')
            ->willReturn($this->producer);

        $this->producer->expects($this->once())
            ->method('send')
            ->with($delayQueue, $delayMessage);

        $this->strategy->delayMessage($this->context, $queue, $this->message, 3000);
    }

    public function testDelayMessageWithXDeathHeader(): void
    {
        $topic = $this->createMock(AmqpTopic::class);
        $delayQueue = $this->createMock(AmqpQueue::class);
        $delayMessage = $this->createMock(AmqpMessage::class);

        $topic->method('getTopicName')->willReturn('test.topic');

        $this->message->method('getBody')->willReturn('test body');
        $this->message->method('getProperties')->willReturn(['x-death' => 'should be removed', 'other' => 'kept']);
        $this->message->method('getHeaders')->willReturn([]);
        $this->message->method('getRoutingKey')->willReturn('test.routing');

        $this->context->expects($this->once())
            ->method('createMessage')
            ->with('test body', ['other' => 'kept'], [])
            ->willReturn($delayMessage);

        $this->context->method('createQueue')->willReturn($delayQueue);
        $this->context->method('createProducer')->willReturn($this->producer);

        $this->strategy->delayMessage($this->context, $topic, $this->message, 1000);
    }
}
