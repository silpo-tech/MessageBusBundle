<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EnqueueProcessor\ExceptionHandler;

use Enqueue\Client\Config;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use MessageBusBundle\EnqueueProcessor\ExceptionHandler\ValidationHandler;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;
use MessageBusBundle\Exception\ValidationException;
use MessageBusBundle\Producer\ProducerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ValidationHandlerTest extends TestCase
{
    private ProducerInterface|MockObject $producer;
    private LoggerInterface|MockObject $logger;
    private Config|MockObject $config;
    private ValidationHandler $handler;

    protected function setUp(): void
    {
        $this->producer = $this->createMock(ProducerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->handler = new ValidationHandler($this->producer, $this->logger, $this->config);
    }

    public function testSupportsValidationException(): void
    {
        $exception = new ValidationException('Test validation error');

        $this->assertTrue($this->handler->supports($exception));
    }

    public function testDoesNotSupportOtherExceptions(): void
    {
        $exception = new \Exception('Test exception');

        $this->assertFalse($this->handler->supports($exception));
    }

    public function testHandle(): void
    {
        $exception = new ValidationException('Validation failed');
        $message = $this->createMock(Message::class);
        $context = $this->createMock(Context::class);
        $processor = $this->createMock(ProcessorInterface::class);

        $message->expects($this->once())
            ->method('getBody')
            ->willReturn('message body');

        $this->config->expects($this->once())
            ->method('getApp')
            ->willReturn('test_app');

        $this->config->expects($this->once())
            ->method('getSeparator')
            ->willReturn('.');

        $this->producer->expects($this->once())
            ->method('sendToQueue')
            ->with('test_app.failed', $this->callback(function ($body) {
                $data = json_decode($body, true);

                return 'Validation failed' === $data['reason']
                       && is_array($data['violations'])
                       && array_key_exists('message', $data)
                       && array_key_exists('correlationId', $data);
            }));

        $this->logger->expects($this->once())
            ->method('debug')
            ->with('Validation failed');

        $result = $this->handler->handle($exception, $message, $context, $processor);

        $this->assertEquals(Processor::ACK, $result);
    }
}
