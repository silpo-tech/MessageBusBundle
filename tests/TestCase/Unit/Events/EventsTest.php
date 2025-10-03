<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Events;

use Interop\Queue\Context;
use Interop\Queue\Message;
use MessageBusBundle\Events\BatchConsumeEvent;
use MessageBusBundle\Events\BatchExceptionConsumeEvent;
use MessageBusBundle\Events\ConsumeEvent;
use MessageBusBundle\Events\ConsumeRejectEvent;
use MessageBusBundle\Events\ConsumeRequeueEvent;
use MessageBusBundle\Events\ConsumeRequeueExceedEvent;
use MessageBusBundle\Events\ExceptionConsumeEvent;
use MessageBusBundle\Events\PreConsumeEvent;
use MessageBusBundle\Events\PrePublishEvent;
use MessageBusBundle\Exception\RejectException;
use MessageBusBundle\Exception\RequeueException;
use PHPUnit\Framework\TestCase;

class EventsTest extends TestCase
{
    private Message $message;
    private Context $context;

    protected function setUp(): void
    {
        $this->message = $this->createMock(Message::class);
        $this->context = $this->createMock(Context::class);
    }

    public function testConsumeEvent(): void
    {
        $body = ['test' => 'data'];
        $processorClass = 'TestProcessor';

        $event = new ConsumeEvent($body, $this->message, $this->context, $processorClass);

        $this->assertEquals($body, $event->getBody());
        $this->assertSame($this->message, $event->getMessage());
        $this->assertSame($this->context, $event->getContext());
        $this->assertEquals($processorClass, $event->getProcessorClass());
    }

    public function testPreConsumeEvent(): void
    {
        $processorClass = 'TestProcessor';

        $event = new PreConsumeEvent($this->message, $this->context, $processorClass);

        $this->assertSame($this->message, $event->getMessage());
        $this->assertSame($this->context, $event->getContext());
        $this->assertEquals($processorClass, $event->getProcessorClass());
    }

    public function testExceptionConsumeEvent(): void
    {
        $exception = new \Exception('Test exception');
        $body = ['test' => 'data'];
        $processorClass = 'TestProcessor';

        $event = new ExceptionConsumeEvent($exception, $body, $this->message, $this->context, $processorClass);

        $this->assertSame($exception, $event->getException());
        $this->assertEquals($body, $event->getBody());
        $this->assertSame($this->message, $event->getMessage());
        $this->assertSame($this->context, $event->getContext());
        $this->assertEquals($processorClass, $event->getProcessorClass());
    }

    public function testBatchConsumeEvent(): void
    {
        $messagesBatch = [$this->message];
        $processorClass = 'TestProcessor';

        $event = new BatchConsumeEvent($messagesBatch, $this->context, $processorClass);

        $this->assertEquals($messagesBatch, $event->getMessagesBatch());
        $this->assertSame($this->context, $event->getContext());
        $this->assertEquals($processorClass, $event->getProcessorClass());
    }

    public function testBatchExceptionConsumeEvent(): void
    {
        $exception = new \Exception('Test exception');
        $messagesBatch = [$this->message];
        $processorClass = 'TestProcessor';

        $event = new BatchExceptionConsumeEvent($exception, $messagesBatch, $this->context, $processorClass);

        $this->assertSame($exception, $event->getException());
        $this->assertEquals($messagesBatch, $event->getMessagesBatch());
        $this->assertSame($this->context, $event->getContext());
        $this->assertEquals($processorClass, $event->getProcessorClass());
    }

    public function testConsumeRejectEvent(): void
    {
        $exception = new RejectException('Reject reason');
        $processorClass = 'TestProcessor';

        $event = new ConsumeRejectEvent($exception, $this->message, $this->context, $processorClass);

        $this->assertSame($exception, $event->exception);
        $this->assertSame($this->message, $event->message);
        $this->assertSame($this->context, $event->context);
        $this->assertEquals($processorClass, $event->processor);
    }

    public function testConsumeRequeueEvent(): void
    {
        $exception = new RequeueException('Requeue reason');
        $processorClass = 'TestProcessor';

        $event = new ConsumeRequeueEvent($exception, $this->message, $this->context, $processorClass, 1);

        $this->assertSame($exception, $event->exception);
        $this->assertSame($this->message, $event->message);
        $this->assertSame($this->context, $event->context);
        $this->assertEquals($processorClass, $event->processor);
    }

    public function testConsumeRequeueExceedEvent(): void
    {
        $exception = new RequeueException('Requeue exceed reason');
        $processorClass = 'TestProcessor';

        $event = new ConsumeRequeueExceedEvent($exception, $this->message, $this->context, $processorClass);

        $this->assertSame($exception, $event->exception);
        $this->assertSame($this->message, $event->message);
        $this->assertSame($this->context, $event->context);
        $this->assertEquals($processorClass, $event->processor);
    }

    public function testPrePublishEvent(): void
    {
        $event = new PrePublishEvent($this->message);

        $this->assertSame($this->message, $event->getMessage());
    }
}
