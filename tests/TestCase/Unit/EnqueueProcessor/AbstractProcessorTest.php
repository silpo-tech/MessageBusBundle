<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EnqueueProcessor;

use Interop\Queue\Context;
use Interop\Queue\Message;
use MapperBundle\Mapper\MapperInterface;
use MessageBusBundle\EnqueueProcessor\AbstractProcessor;
use MessageBusBundle\EnqueueProcessor\ExceptionHandler\ChainExceptionHandler;
use MessageBusBundle\Service\ProducerService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AbstractProcessorTest extends TestCase
{
    private MapperInterface|MockObject $mapper;
    private ChainExceptionHandler|MockObject $chainExceptionHandler;
    private EventDispatcherInterface|MockObject $eventDispatcher;
    private Context|MockObject $context;
    private Message|MockObject $message;
    private TestProcessor $processor;

    protected function setUp(): void
    {
        $this->mapper = $this->createMock(MapperInterface::class);
        $this->chainExceptionHandler = $this->createMock(ChainExceptionHandler::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->context = $this->createMock(Context::class);
        $this->message = $this->createMock(Message::class);

        $this->processor = new TestProcessor();
        $this->processor->setMapper($this->mapper);
        $this->processor->setChainExceptionHandler($this->chainExceptionHandler);
        $this->processor->setEventDispatcher($this->eventDispatcher);
    }

    public function testSetMapper(): void
    {
        $mapper = $this->createMock(MapperInterface::class);
        $result = $this->processor->setMapper($mapper);

        $this->assertSame($this->processor, $result);
    }

    public function testSetChainExceptionHandler(): void
    {
        $handler = $this->createMock(ChainExceptionHandler::class);
        $result = $this->processor->setChainExceptionHandler($handler);

        $this->assertSame($this->processor, $result);
    }

    public function testSetEventDispatcher(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $result = $this->processor->setEventDispatcher($eventDispatcher);

        $this->assertSame($this->processor, $result);
    }

    public function testSetLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $this->processor->setLogger($logger);

        $this->assertTrue(true);
    }

    public function testProcessSuccess(): void
    {
        $body = ['test' => 'data'];
        $this->message->method('getBody')->willReturn(json_encode($body));
        $this->message->method('getProperty')->willReturn(null);

        $this->processor->setDoProcessResult('ack');

        $this->eventDispatcher->expects($this->exactly(3))
            ->method('dispatch');

        $result = $this->processor->process($this->message, $this->context);

        $this->assertEquals('ack', $result);
    }

    public function testProcessWithClassHeader(): void
    {
        $body = ['test' => 'data'];
        $destinationClass = 'stdClass';
        $convertedBody = new \stdClass();

        $this->message->method('getBody')->willReturn(json_encode($body));
        $this->message->expects($this->once())
            ->method('getProperty')
            ->with(ProducerService::CLASS_HEADER)
            ->willReturn($destinationClass);

        $this->mapper->expects($this->once())
            ->method('convert')
            ->with($body, $destinationClass)
            ->willReturn($convertedBody);

        $this->processor->setDoProcessResult('ack');

        $result = $this->processor->process($this->message, $this->context);

        $this->assertEquals('ack', $result);
    }

    public function testProcessWithException(): void
    {
        $exception = new \Exception('Test exception');

        $this->message->method('getBody')->willReturn('{}');
        $this->message->method('getProperty')->willReturn(null);

        $this->processor->setDoProcessException($exception);

        $this->chainExceptionHandler->expects($this->once())
            ->method('handle')
            ->with($this->processor, $exception, $this->message, $this->context)
            ->willReturn('reject');

        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch');

        $result = $this->processor->process($this->message, $this->context);

        $this->assertEquals('reject', $result);
    }

    public function testProcessReturnsAckByDefault(): void
    {
        $this->message->method('getBody')->willReturn('{}');
        $this->message->method('getProperty')->willReturn(null);

        $this->processor->setDoProcessResult(null);

        $result = $this->processor->process($this->message, $this->context);

        $this->assertEquals(AbstractProcessor::ACK, $result);
    }

    public function testTryConvertBodyWithExistingClass(): void
    {
        $source = ['test' => 'data'];
        $destination = \stdClass::class;
        $converted = new \stdClass();

        $this->mapper->expects($this->once())
            ->method('convert')
            ->with($source, $destination)
            ->willReturn($converted);

        $result = $this->processor->tryConvertBodyPublic($source, $destination);

        $this->assertSame($converted, $result);
    }

    public function testTryConvertBodyWithNonExistingClass(): void
    {
        $source = ['test' => 'data'];
        $destination = 'NonExistentClass';

        $result = $this->processor->tryConvertBodyPublic($source, $destination);

        $this->assertSame($source, $result);
    }
}

class TestProcessor extends AbstractProcessor
{
    private $doProcessResult = 'ack';
    private ?\Throwable $doProcessException = null;

    public function setDoProcessResult($result): void
    {
        $this->doProcessResult = $result;
    }

    public function setDoProcessException(\Throwable $exception): void
    {
        $this->doProcessException = $exception;
    }

    public function doProcess($body, Message $message, Context $session)
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

    public function tryConvertBodyPublic($source, $destination)
    {
        return $this->tryConvertBody($source, $destination);
    }
}
