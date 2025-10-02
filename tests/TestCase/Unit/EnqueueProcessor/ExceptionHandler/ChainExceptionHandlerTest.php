<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EnqueueProcessor\ExceptionHandler;

use Interop\Queue\Context;
use Interop\Queue\Message;
use MessageBusBundle\EnqueueProcessor\ExceptionHandler\ChainExceptionHandler;
use MessageBusBundle\EnqueueProcessor\ExceptionHandler\ExceptionHandlerInterface;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ChainExceptionHandlerTest extends TestCase
{
    private ChainExceptionHandler $chainHandler;
    private ProcessorInterface|MockObject $processor;
    private Message|MockObject $message;
    private Context|MockObject $context;

    protected function setUp(): void
    {
        $this->chainHandler = new ChainExceptionHandler();
        $this->processor = $this->createMock(ProcessorInterface::class);
        $this->message = $this->createMock(Message::class);
        $this->context = $this->createMock(Context::class);
    }

    public function testAddHandler(): void
    {
        $handler = $this->createMock(ExceptionHandlerInterface::class);

        $result = $this->chainHandler->addHandler($handler, 10);

        $this->assertSame($this->chainHandler, $result);
    }

    public function testHandleWithSupportingHandler(): void
    {
        $exception = new \Exception('Test exception');
        $handler = $this->createMock(ExceptionHandlerInterface::class);

        $handler->expects($this->once())
            ->method('supports')
            ->with($exception)
            ->willReturn(true);

        $handler->expects($this->once())
            ->method('handle')
            ->with($exception, $this->message, $this->context, $this->processor)
            ->willReturn('handled');

        $this->chainHandler->addHandler($handler, 10);

        $result = $this->chainHandler->handle($this->processor, $exception, $this->message, $this->context);

        $this->assertEquals('handled', $result);
    }

    public function testHandleWithNonSupportingHandler(): void
    {
        $exception = new \Exception('Test exception');
        $handler = $this->createMock(ExceptionHandlerInterface::class);

        $handler->expects($this->once())
            ->method('supports')
            ->with($exception)
            ->willReturn(false);

        $handler->expects($this->never())
            ->method('handle');

        $this->chainHandler->addHandler($handler, 10);

        $result = $this->chainHandler->handle($this->processor, $exception, $this->message, $this->context);

        $this->assertNull($result);
    }

    public function testHandleWithMultipleHandlersByPriority(): void
    {
        $exception = new \Exception('Test exception');
        $handler1 = $this->createMock(ExceptionHandlerInterface::class);
        $handler2 = $this->createMock(ExceptionHandlerInterface::class);

        // Handler with higher priority should be called first
        $handler2->expects($this->once())
            ->method('supports')
            ->with($exception)
            ->willReturn(true);

        $handler2->expects($this->once())
            ->method('handle')
            ->willReturn('handled_by_2');

        $handler1->expects($this->never())
            ->method('supports');

        $this->chainHandler->addHandler($handler1, 5);
        $this->chainHandler->addHandler($handler2, 10);

        $result = $this->chainHandler->handle($this->processor, $exception, $this->message, $this->context);

        $this->assertEquals('handled_by_2', $result);
    }
}
