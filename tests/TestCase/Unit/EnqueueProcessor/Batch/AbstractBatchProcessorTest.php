<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EnqueueProcessor\Batch;

use Enqueue\Consumption\Result as EnqueueResult;
use Interop\Amqp\AmqpMessage;
use Interop\Queue\Context;
use MessageBusBundle\EnqueueProcessor\Batch\AbstractBatchProcessor;
use MessageBusBundle\EnqueueProcessor\Batch\Result;
use MessageBusBundle\EnqueueProcessor\ExceptionHandler\ChainExceptionHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AbstractBatchProcessorTest extends TestCase
{
    public function testProcessSuccess(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $context = $this->createMock(Context::class);
        $messages = [$this->createMock(AmqpMessage::class)];
        $expectedResults = [Result::ack(0)];

        $processor = $this->createProcessor($expectedResults);
        $processor->setEventDispatcher($eventDispatcher);

        $eventDispatcher->expects($this->exactly(2))
            ->method('dispatch');

        $result = $processor->process($messages, $context);

        $this->assertEquals($expectedResults, $result);
    }

    public function testProcessWithRequeueHandledAsAck(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $chainExceptionHandler = $this->createMock(ChainExceptionHandler::class);
        $context = $this->createMock(Context::class);
        $messages = [$this->createMock(AmqpMessage::class)];
        $exception = new \Exception('Test exception');
        $results = [Result::requeue(0, $exception)];

        $processor = $this->createProcessor($results);
        $processor->setEventDispatcher($eventDispatcher);
        $processor->setChainExceptionHandler($chainExceptionHandler);

        $chainExceptionHandler->expects($this->once())
            ->method('handle')
            ->willReturn(EnqueueResult::ACK);

        $result = $processor->process($messages, $context);

        $this->assertEquals([Result::ack(0)], $result);
    }

    public function testProcessWithGeneralException(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $chainExceptionHandler = $this->createMock(ChainExceptionHandler::class);
        $context = $this->createMock(Context::class);
        $messages = [$this->createMock(AmqpMessage::class)];
        $exception = new \RuntimeException('General error');

        $processor = $this->createProcessorWithException($exception);
        $processor->setEventDispatcher($eventDispatcher);
        $processor->setChainExceptionHandler($chainExceptionHandler);

        $eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch');

        $chainExceptionHandler->expects($this->once())
            ->method('handle');

        $result = $processor->process($messages, $context);

        $this->assertEquals([], $result);
    }

    private function createProcessor(array $results): AbstractBatchProcessor
    {
        return new class($results) extends AbstractBatchProcessor {
            public function __construct(private array $results)
            {
            }

            public function doProcess(array $messages, Context $session): array
            {
                return $this->results;
            }

            public function getSubscribedRoutingKeys(): array
            {
                return [];
            }
        };
    }

    private function createProcessorWithException(\Throwable $exception): AbstractBatchProcessor
    {
        return new class($exception) extends AbstractBatchProcessor {
            public function __construct(private \Throwable $exception)
            {
            }

            public function doProcess(array $messages, Context $session): array
            {
                throw $this->exception;
            }

            public function getSubscribedRoutingKeys(): array
            {
                return [];
            }
        };
    }
}
