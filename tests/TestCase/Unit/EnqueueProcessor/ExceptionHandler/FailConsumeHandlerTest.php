<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EnqueueProcessor\ExceptionHandler;

use Enqueue\Client\Config;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use MessageBusBundle\EnqueueProcessor\ExceptionHandler\FailConsumeHandler;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;
use MessageBusBundle\Producer\ProducerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FailConsumeHandlerTest extends TestCase
{
    private ProducerInterface|MockObject $producer;
    private LoggerInterface|MockObject $logger;
    private Config|MockObject $config;
    private FailConsumeHandler $handler;

    protected function setUp(): void
    {
        $this->producer = $this->createMock(ProducerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->handler = new FailConsumeHandler($this->producer, $this->logger, $this->config);
    }

    public function testSupportsAllExceptions(): void
    {
        $exception = new \Exception('Test exception');

        $this->assertTrue($this->handler->supports($exception));
    }

    public function testHandle(): void
    {
        $exception = new \Exception('Test exception');
        $message = $this->createMock(Message::class);
        $context = $this->createMock(Context::class);
        $processor = $this->createMock(ProcessorInterface::class);

        $message->expects($this->once())
            ->method('getBody')
            ->willReturn('message body');

        $processor->expects($this->once())
            ->method('getSubscribedRoutingKeys')
            ->willReturn(['test_queue' => []]);

        $this->config->expects($this->once())
            ->method('getSeparator')
            ->willReturn('.');

        $this->producer->expects($this->once())
            ->method('sendMessageToQueue')
            ->with('test_queue.exception.failed', $message);

        $this->logger->expects($this->any())
            ->method('error');

        $result = $this->handler->handle($exception, $message, $context, $processor);

        $this->assertEquals(Processor::REJECT, $result);
    }
}
