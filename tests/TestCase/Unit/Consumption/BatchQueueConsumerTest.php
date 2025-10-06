<?php

namespace MessageBusBundle\Tests\TestCase\Unit\Consumption;

use Enqueue\Consumption\Exception\LogicException;
use Interop\Queue\Context;
use Interop\Queue\Queue;
use Interop\Queue\SubscriptionConsumer;
use MessageBusBundle\Consumption\BatchBoundProcessor;
use MessageBusBundle\Consumption\BatchQueueConsumer;
use MessageBusBundle\EnqueueProcessor\Batch\BatchProcessorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BatchQueueConsumerTest extends TestCase
{
    private Context $context;
    private LoggerInterface $logger;
    private BatchQueueConsumer $consumer;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->consumer = new BatchQueueConsumer(
            [$this->context],
            [],
            $this->logger,
            1000,
            10
        );
    }

    public function testBindWithQueueName(): void
    {
        $queue = $this->createMock(Queue::class);
        $queue->method('getQueueName')->willReturn('test_queue');

        $processor = $this->createMock(BatchProcessorInterface::class);

        $this->context->expects($this->once())
            ->method('createQueue')
            ->with('test_queue')
            ->willReturn($queue);

        $result = $this->consumer->bind('test_queue', $processor);

        $this->assertSame($this->consumer, $result);
    }

    public function testBindWithQueueObject(): void
    {
        $queue = $this->createMock(Queue::class);
        $queue->method('getQueueName')->willReturn('test_queue');

        $processor = $this->createMock(BatchProcessorInterface::class);

        $result = $this->consumer->bind($queue, $processor);

        $this->assertSame($this->consumer, $result);
    }

    public function testBindCallback(): void
    {
        $queue = $this->createMock(Queue::class);
        $queue->method('getQueueName')->willReturn('test_queue');

        $this->context->expects($this->once())
            ->method('createQueue')
            ->with('test_queue')
            ->willReturn($queue);

        $callback = function () {};
        $result = $this->consumer->bindCallback('test_queue', $callback);

        $this->assertSame($this->consumer, $result);
    }

    public function testBindThrowsExceptionForEmptyQueueName(): void
    {
        $queue = $this->createMock(Queue::class);
        $queue->method('getQueueName')->willReturn('');

        $processor = $this->createMock(BatchProcessorInterface::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The queue name must be not empty.');

        $this->consumer->bind($queue, $processor);
    }

    public function testBindThrowsExceptionForDuplicateQueue(): void
    {
        $queue = $this->createMock(Queue::class);
        $queue->method('getQueueName')->willReturn('test_queue');

        $processor = $this->createMock(BatchProcessorInterface::class);

        $this->consumer->bind($queue, $processor);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The queue was already bound. Queue: test_queue');

        $this->consumer->bind($queue, $processor);
    }

    public function testConsumeThrowsExceptionWhenNothingBound(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('There is nothing to consume. It is required to bind something before calling consume method.');

        $this->consumer->consume();
    }

    public function testSetFallbackSubscriptionConsumer(): void
    {
        $fallbackConsumer = $this->createMock(SubscriptionConsumer::class);

        $this->consumer->setFallbackSubscriptionConsumer($fallbackConsumer);

        $this->assertTrue(true);
    }

    public function testConstructorWithBoundProcessors(): void
    {
        $boundProcessor = new BatchBoundProcessor($this->createMock(Queue::class), $this->createMock(BatchProcessorInterface::class));
        $consumer = new BatchQueueConsumer(
            [$this->context],
            [$boundProcessor],
            $this->logger,
            1000,
            10
        );

        $this->assertInstanceOf(BatchQueueConsumer::class, $consumer);
    }
}
