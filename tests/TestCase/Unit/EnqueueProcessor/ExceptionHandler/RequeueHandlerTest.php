<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EnqueueProcessor\ExceptionHandler;

use Enqueue\Client\Config;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use MessageBusBundle\EnqueueProcessor\ExceptionHandler\RequeueHandler;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;
use MessageBusBundle\Events\ConsumeRequeueEvent;
use MessageBusBundle\Events\ConsumeRequeueExceedEvent;
use MessageBusBundle\Exception\RequeueException;
use MessageBusBundle\Producer\ProducerInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

class RequeueHandlerTest extends TestCase
{
    private ProducerInterface $producer;
    private Config $config;
    private LoggerInterface $logger;
    private EventDispatcherInterface $eventDispatcher;
    private RequeueHandler $handler;

    protected function setUp(): void
    {
        $this->producer = $this->createMock(ProducerInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->handler = new RequeueHandler(
            $this->producer,
            $this->config,
            $this->logger,
            $this->eventDispatcher
        );
    }

    public function testHandleRequeueWithinLimit(): void
    {
        $exception = new RequeueException('Test requeue', 3);
        $message = $this->createMock(Message::class);
        $context = $this->createMock(Context::class);
        $processor = $this->createMock(ProcessorInterface::class);

        $message->expects($this->once())->method('getProperty')->with('requeue-count', 0)->willReturn(1);
        $message->expects($this->exactly(2))->method('setProperty');
        $processor->expects($this->once())->method('getSubscribedRoutingKeys')->willReturn(['test.queue' => []]);

        $this->producer->expects($this->once())->method('sendMessageToQueue')->with('test.queue', $message, 4000);
        $this->eventDispatcher->expects($this->once())->method('dispatch')->with($this->isInstanceOf(ConsumeRequeueEvent::class));
        $this->logger->expects($this->once())->method('debug');

        $result = $this->handler->handle($exception, $message, $context, $processor);

        $this->assertEquals(Processor::ACK, $result);
    }

    public function testHandleRequeueExceedLimit(): void
    {
        $exception = new RequeueException('Test requeue', 2);
        $message = $this->createMock(Message::class);
        $context = $this->createMock(Context::class);
        $processor = $this->createMock(ProcessorInterface::class);

        $message->expects($this->once())->method('getProperty')->with('requeue-count', 0)->willReturn(2);
        $processor->expects($this->once())->method('getSubscribedRoutingKeys')->willReturn(['test.queue' => []]);
        $this->config->expects($this->once())->method('getSeparator')->willReturn('.');

        $this->producer->expects($this->once())->method('sendMessageToQueue')->with('test.queue.failed', $message);
        $this->eventDispatcher->expects($this->once())->method('dispatch')->with($this->isInstanceOf(ConsumeRequeueExceedEvent::class));
        $this->logger->expects($this->once())->method('error');

        $result = $this->handler->handle($exception, $message, $context, $processor);

        $this->assertEquals(Processor::ACK, $result);
    }

    public function testSupportsRequeueException(): void
    {
        $exception = new RequeueException('Test');

        $this->assertTrue($this->handler->supports($exception));
    }

    public function testDoesNotSupportOtherExceptions(): void
    {
        $exception = new \Exception('Test');

        $this->assertFalse($this->handler->supports($exception));
    }
}
