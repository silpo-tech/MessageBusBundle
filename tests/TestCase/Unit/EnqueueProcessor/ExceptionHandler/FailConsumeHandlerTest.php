<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EnqueueProcessor\ExceptionHandler;

use Enqueue\Client\Config;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use MessageBusBundle\AmqpTools\QueueType;
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

        $message->expects($this->any())
            ->method('getProperties')
            ->willReturn([]);

        $message->expects($this->any())
            ->method('getHeaders')
            ->willReturn([]);

        $setPropertyCalls = [];
        $message->expects($this->atLeastOnce())
            ->method('setProperty')
            ->willReturnCallback(function (string $key, mixed $value) use (&$setPropertyCalls): void {
                $setPropertyCalls[$key] = $value;
            });

        $processor->expects($this->once())
            ->method('getSubscribedRoutingKeys')
            ->willReturn(['test_queue' => []]);

        $processor->expects($this->once())
            ->method('getQueueType')
            ->willReturn(QueueType::QUORUM);

        $this->config->expects($this->once())
            ->method('getSeparator')
            ->willReturn('.');

        $this->producer->expects($this->once())
            ->method('sendMessageToQueue')
            ->with('test_queue.exception.failed', $message, 0, QueueType::QUORUM);

        $this->logger->expects($this->any())
            ->method('error');

        $result = $this->handler->handle($exception, $message, $context, $processor);

        $this->assertEquals(Processor::REJECT, $result);

        $this->assertArrayHasKey('x-exception-log-datetime', $setPropertyCalls);
        $this->assertArrayHasKey('x-exception-trace', $setPropertyCalls);
        $this->assertArrayHasKey('x-exception-class', $setPropertyCalls);
        $this->assertArrayHasKey('x-exception-message', $setPropertyCalls);
        $this->assertArrayHasKey('x-exception-file', $setPropertyCalls);
        $this->assertArrayHasKey('x-exception-line', $setPropertyCalls);

        $this->assertEquals(\Exception::class, $setPropertyCalls['x-exception-class']);
        $this->assertEquals('Test exception', $setPropertyCalls['x-exception-message']);
        $this->assertEquals($exception->getFile(), $setPropertyCalls['x-exception-file']);
        $this->assertEquals($exception->getLine(), $setPropertyCalls['x-exception-line']);
    }
}
