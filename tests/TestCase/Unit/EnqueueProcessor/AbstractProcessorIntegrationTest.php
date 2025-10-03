<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EnqueueProcessor;

use Interop\Queue\Context;
use Interop\Queue\Message;
use MapperBundle\Mapper\MapperInterface;
use MessageBusBundle\EnqueueProcessor\AbstractProcessor;
use MessageBusBundle\EnqueueProcessor\ExceptionHandler\ChainExceptionHandler;
use MessageBusBundle\Events;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class AbstractProcessorIntegrationTest extends TestCase
{
    private MapperInterface|MockObject $mapper;
    private ChainExceptionHandler|MockObject $chainExceptionHandler;
    private EventDispatcher $eventDispatcher;
    private Context|MockObject $context;
    private Message|MockObject $message;
    private TestIntegrationProcessor $processor;
    private array $dispatchedEvents = [];

    protected function setUp(): void
    {
        $this->mapper = $this->createMock(MapperInterface::class);
        $this->chainExceptionHandler = $this->createMock(ChainExceptionHandler::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->context = $this->createMock(Context::class);
        $this->message = $this->createMock(Message::class);

        // Add listener to track dispatched events
        $this->eventDispatcher->addListener(Events::CONSUME__PRE_START, function ($event) {
            $this->dispatchedEvents[] = [Events::CONSUME__PRE_START, get_class($event)];
        });

        $this->eventDispatcher->addListener(Events::CONSUME__START, function ($event) {
            $this->dispatchedEvents[] = [Events::CONSUME__START, get_class($event)];
        });

        $this->eventDispatcher->addListener(Events::CONSUME__FINISHED, function ($event) {
            $this->dispatchedEvents[] = [Events::CONSUME__FINISHED, get_class($event)];
        });

        $this->eventDispatcher->addListener(Events::CONSUME__EXCEPTION, function ($event) {
            $this->dispatchedEvents[] = [Events::CONSUME__EXCEPTION, get_class($event)];
        });

        $this->processor = new TestIntegrationProcessor();
        $this->processor->setMapper($this->mapper);
        $this->processor->setChainExceptionHandler($this->chainExceptionHandler);
        $this->processor->setEventDispatcher($this->eventDispatcher);
    }

    public function testProcessWithRealEventDispatcher(): void
    {
        $body = ['test' => 'data'];
        $this->message->method('getBody')->willReturn(json_encode($body));
        $this->message->method('getProperty')->willReturn(null);

        $this->processor->setDoProcessResult('ack');

        $result = $this->processor->process($this->message, $this->context);

        $this->assertEquals('ack', $result);

        // Verify events were dispatched
        $this->assertCount(3, $this->dispatchedEvents);
        $this->assertEquals(Events::CONSUME__PRE_START, $this->dispatchedEvents[0][0]);
        $this->assertEquals('MessageBusBundle\\Events\\PreConsumeEvent', $this->dispatchedEvents[0][1]);
        $this->assertEquals(Events::CONSUME__START, $this->dispatchedEvents[1][0]);
        $this->assertEquals('MessageBusBundle\\Events\\ConsumeEvent', $this->dispatchedEvents[1][1]);
        $this->assertEquals(Events::CONSUME__FINISHED, $this->dispatchedEvents[2][0]);
        $this->assertEquals('MessageBusBundle\\Events\\ConsumeEvent', $this->dispatchedEvents[2][1]);
    }

    public function testProcessWithExceptionAndRealEventDispatcher(): void
    {
        $exception = new \Exception('Test exception');

        $this->message->method('getBody')->willReturn('{}');
        $this->message->method('getProperty')->willReturn(null);

        $this->processor->setDoProcessException($exception);

        $this->chainExceptionHandler->expects($this->once())
            ->method('handle')
            ->willReturn('reject');

        $result = $this->processor->process($this->message, $this->context);

        $this->assertEquals('reject', $result);

        // Verify exception event was dispatched
        $exceptionEvents = array_filter($this->dispatchedEvents, function ($event) {
            return Events::CONSUME__EXCEPTION === $event[0];
        });
        $this->assertCount(1, $exceptionEvents);
        $this->assertEquals('MessageBusBundle\\Events\\ExceptionConsumeEvent', array_values($exceptionEvents)[0][1]);
    }
}

class TestIntegrationProcessor extends AbstractProcessor
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
}
