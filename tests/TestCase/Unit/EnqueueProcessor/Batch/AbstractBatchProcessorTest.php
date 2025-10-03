<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EnqueueProcessor\Batch;

use Enqueue\Consumption\Result as EnqueueResult;
use Interop\Amqp\AmqpMessage;
use Interop\Queue\Context;
use MessageBusBundle\EnqueueProcessor\Batch\AbstractBatchProcessor;
use MessageBusBundle\EnqueueProcessor\Batch\Result;
use MessageBusBundle\EnqueueProcessor\ExceptionHandler\ChainExceptionHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AbstractBatchProcessorTest extends TestCase
{
    private EventDispatcherInterface|MockObject $eventDispatcher;
    private ChainExceptionHandler|MockObject $chainExceptionHandler;
    private Context|MockObject $context;
    private TestBatchProcessor $processor;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->chainExceptionHandler = $this->createMock(ChainExceptionHandler::class);
        $this->context = $this->createMock(Context::class);

        $this->processor = new TestBatchProcessor();
        $this->processor->setEventDispatcher($this->eventDispatcher);
        $this->processor->setChainExceptionHandler($this->chainExceptionHandler);
    }

    public function testSetEventDispatcher(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $result = $this->processor->setEventDispatcher($eventDispatcher);

        $this->assertSame($this->processor, $result);
    }

    public function testSetChainExceptionHandler(): void
    {
        $handler = $this->createMock(ChainExceptionHandler::class);
        $result = $this->processor->setChainExceptionHandler($handler);

        $this->assertSame($this->processor, $result);
    }

    public function testProcessSuccess(): void
    {
        $messages = [$this->createMock(AmqpMessage::class)];
        $results = [Result::ack(0)];

        $this->processor->setDoProcessResult($results);

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch');

        $result = $this->processor->process($messages, $this->context);

        $this->assertEquals($results, $result);
    }

    public function testProcessWithRequeueHandledAsAck(): void
    {
        $messages = [$this->createMock(AmqpMessage::class)];
        $exception = new \Exception('Test exception');
        $results = [Result::requeue(0, $exception)];

        $this->processor->setDoProcessResult($results);

        $this->chainExceptionHandler->expects($this->once())
            ->method('handle')
            ->willReturn(EnqueueResult::ACK);

        $result = $this->processor->process($messages, $this->context);

        $this->assertEquals([Result::ack(0)], $result);
    }

    public function testProcessWithGeneralException(): void
    {
        $messages = [$this->createMock(AmqpMessage::class)];
        $exception = new \RuntimeException('General error');

        $this->processor->setDoProcessException($exception);

        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch');

        $this->chainExceptionHandler->expects($this->once())
            ->method('handle');

        $result = $this->processor->process($messages, $this->context);

        $this->assertEquals([], $result);
    }

    public function testGetDefaultIndexName(): void
    {
        $result = AbstractBatchProcessor::getDefaultIndexName();

        $this->assertEquals(AbstractBatchProcessor::class, $result);
    }

    public function testBatchProcessorTag(): void
    {
        $this->assertEquals('messagesbus.batch_processor', AbstractBatchProcessor::BATCH_PROCESSOR_TAG);
    }
}

class TestBatchProcessor extends AbstractBatchProcessor
{
    private array $doProcessResult = [];
    private ?\Throwable $doProcessException = null;

    public function setDoProcessResult(array $result): void
    {
        $this->doProcessResult = $result;
    }

    public function setDoProcessException(\Throwable $exception): void
    {
        $this->doProcessException = $exception;
    }

    public function doProcess(array $messages, Context $session): array
    {
        if ($this->doProcessException) {
            throw $this->doProcessException;
        }

        return $this->doProcessResult;
    }

    public function getSubscribedRoutingKeys(): array
    {
        return [];
    }
}
