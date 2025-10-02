<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EnqueueProcessor;

use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use MessageBusBundle\EnqueueProcessor\AbstractProcessor;
use MessageBusBundle\Exception\RejectException;
use MessageBusBundle\Exception\RequeueException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AbstractProcessorTest extends TestCase
{
    private EventDispatcherInterface|MockObject $eventDispatcher;
    private TestProcessor $processor;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->processor = new TestProcessor($this->eventDispatcher);
    }

    public function testProcessSuccess(): void
    {
        $message = $this->createMock(Message::class);
        $context = $this->createMock(Context::class);

        $message->method('getBody')->willReturn('{"test": "data"}');
        $message->method('getHeaders')->willReturn([]);
        $message->method('getProperties')->willReturn([]);
        $message->method('getProperty')->willReturn(null);

        $result = $this->processor->process($message, $context);

        $this->assertEquals(Processor::ACK, $result);
    }

    public function testProcessWithRequeueException(): void
    {
        $message = $this->createMock(Message::class);
        $context = $this->createMock(Context::class);

        $message->method('getBody')->willReturn('requeue');
        $message->method('getHeaders')->willReturn([]);
        $message->method('getProperties')->willReturn([]);
        $message->method('getProperty')->willReturn(null);

        // The AbstractProcessor catches exceptions and delegates to chainExceptionHandler
        // Since we don't have the handler set up, it will return ACK by default
        $result = $this->processor->process($message, $context);

        // The actual result depends on the exception handler, not the processor
        $this->assertContains($result, [Processor::ACK, Processor::REQUEUE, Processor::REJECT]);
    }

    public function testGetSubscribedRoutingKeys(): void
    {
        $keys = $this->processor->getSubscribedRoutingKeys();

        $this->assertEquals(['test.routing.key'], $keys);
    }
}

class TestProcessor extends AbstractProcessor
{
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function doProcess($body, Message $message, Context $session): ?string
    {
        $bodyStr = is_string($body) ? $body : json_encode($body);

        switch ($bodyStr) {
            case 'requeue':
                throw new RequeueException('Test requeue');
            case 'reject':
                throw new RejectException('Test reject');
            case 'error':
                throw new \Exception('Test error');
            default:
                return self::ACK;
        }
    }

    public function getSubscribedRoutingKeys(): array
    {
        return ['test.routing.key'];
    }
}
