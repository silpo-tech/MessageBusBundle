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
use Symfony\Component\Validator\ConstraintViolation;

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

        $result = $this->handler->handle($exception, $message, $context, $processor);

        $this->assertEquals(Processor::ACK, $result);
    }

    public function testHandleWithConstraintViolations(): void
    {
        $violation = $this->createMock(ConstraintViolation::class);
        $violation->method('getPropertyPath')->willReturn('field');
        $violation->method('getMessage')->willReturn('Field is required');

        $exception = new ValidationException('Validation failed', [$violation]);
        $message = $this->createMock(Message::class);
        $context = $this->createMock(Context::class);
        $processor = $this->createMock(ProcessorInterface::class);

        $message->method('getBody')->willReturn('{}');
        $message->method('getCorrelationId')->willReturn('test-id');

        $this->config->method('getApp')->willReturn('test_app');
        $this->config->method('getSeparator')->willReturn('.');

        $this->producer->expects($this->once())
            ->method('sendToQueue')
            ->with('test_app.failed', $this->callback(function ($body) {
                $data = json_decode($body, true);

                return isset($data['violations'][0]['field'])
                       && 'Field is required' === $data['violations'][0]['field'];
            }));

        $result = $this->handler->handle($exception, $message, $context, $processor);

        $this->assertEquals(Processor::ACK, $result);
    }

    public function testHandleWithNestedViolations(): void
    {
        $nestedViolations = ['nested' => 'error'];
        $exception = new ValidationException('Validation failed', ['parent' => $nestedViolations]);
        $message = $this->createMock(Message::class);
        $context = $this->createMock(Context::class);
        $processor = $this->createMock(ProcessorInterface::class);

        $message->method('getBody')->willReturn('{}');
        $message->method('getCorrelationId')->willReturn('test-id');

        $this->config->method('getApp')->willReturn('test_app');
        $this->config->method('getSeparator')->willReturn('.');

        $this->producer->expects($this->once())
            ->method('sendToQueue');

        $result = $this->handler->handle($exception, $message, $context, $processor);

        $this->assertEquals(Processor::ACK, $result);
    }

    public function testCreateQueueName(): void
    {
        $this->config->method('getApp')->willReturn('myapp');
        $this->config->method('getSeparator')->willReturn('-');

        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('createQueueName');
        $method->setAccessible(true);

        $result = $method->invoke($this->handler);

        $this->assertEquals('myapp-failed', $result);
    }

    public function testAdoptViolationsWithSimpleValues(): void
    {
        $violations = ['field1' => 'error1', 'field2' => 'error2'];
        $exception = new ValidationException('Validation failed', $violations);
        $message = $this->createMock(Message::class);
        $context = $this->createMock(Context::class);
        $processor = $this->createMock(ProcessorInterface::class);

        $message->method('getBody')->willReturn('{}');
        $message->method('getCorrelationId')->willReturn('test-id');

        $this->config->method('getApp')->willReturn('test');
        $this->config->method('getSeparator')->willReturn('.');

        $this->producer->expects($this->once())
            ->method('sendToQueue');

        $result = $this->handler->handle($exception, $message, $context, $processor);

        $this->assertEquals(Processor::ACK, $result);
    }
}
