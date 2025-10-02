<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EnqueueProcessor\ExceptionHandler;

use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use MessageBusBundle\EnqueueProcessor\ExceptionHandler\RejectHandler;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;
use MessageBusBundle\Events\ConsumeRejectEvent;
use MessageBusBundle\Exception\RejectException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

class RejectHandlerTest extends TestCase
{
    private LoggerInterface|MockObject $logger;
    private EventDispatcherInterface|MockObject $eventDispatcher;
    private RejectHandler $handler;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->handler = new RejectHandler($this->logger, $this->eventDispatcher);
    }

    public function testSupportsRejectException(): void
    {
        $exception = new RejectException('Test reject');

        $this->assertTrue($this->handler->supports($exception));
    }

    public function testDoesNotSupportOtherExceptions(): void
    {
        $exception = new \Exception('Test exception');

        $this->assertFalse($this->handler->supports($exception));
    }

    public function testHandle(): void
    {
        $exception = new RejectException('Test reject message');
        $message = $this->createMock(Message::class);
        $context = $this->createMock(Context::class);
        $processor = $this->createMock(ProcessorInterface::class);

        $message->expects($this->once())
            ->method('getBody')
            ->willReturn('message body');

        $message->expects($this->once())
            ->method('getCorrelationId')
            ->willReturn('correlation-123');

        $this->logger->expects($this->once())
            ->method('debug')
            ->with('[MessageBusBundle] Reject message', $this->callback(function ($context) {
                return isset($context['reason'])
                    && isset($context['trace'])
                    && isset($context['message'])
                    && isset($context['correlationId']);
            }));

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ConsumeRejectEvent::class));

        $result = $this->handler->handle($exception, $message, $context, $processor);

        $this->assertEquals(Processor::REJECT, $result);
    }
}
