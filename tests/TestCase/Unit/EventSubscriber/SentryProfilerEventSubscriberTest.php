<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EventSubscriber;

use MessageBusBundle\Events;
use MessageBusBundle\Events\BatchConsumeEvent;
use MessageBusBundle\Events\PreConsumeEvent;
use MessageBusBundle\EventSubscriber\SentryProfilerEventSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SentryProfilerEventSubscriberTest extends TestCase
{
    private SentryProfilerEventSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new SentryProfilerEventSubscriber();
    }

    public function testImplementsEventSubscriberInterface(): void
    {
        $this->assertInstanceOf(EventSubscriberInterface::class, $this->subscriber);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = SentryProfilerEventSubscriber::getSubscribedEvents();

        $expectedEvents = [
            Events::BATCH_CONSUME__START => 'startTransaction',
            Events::BATCH_CONSUME__FINISHED => 'successTransaction',
            Events::BATCH_CONSUME__EXCEPTION => 'errorTransaction',
            Events::CONSUME__PRE_START => 'startAnonymousTransaction',
            Events::CONSUME__FINISHED => 'successTransaction',
            Events::CONSUME__EXCEPTION => 'errorTransaction',
        ];

        $this->assertEquals($expectedEvents, $events);
    }

    public function testStartTransactionWithoutSentry(): void
    {
        $event = $this->createMock(BatchConsumeEvent::class);
        $event->method('getProcessorClass')->willReturn('TestProcessor');
        $event->method('getMessagesBatch')->willReturn(['msg1', 'msg2']);

        // Should not throw exception when Sentry classes don't exist
        $this->subscriber->startTransaction($event);

        $this->assertTrue(true); // No exception thrown
    }

    public function testStartAnonymousTransactionWithoutSentry(): void
    {
        $event = $this->createMock(PreConsumeEvent::class);
        $event->method('getProcessorClass')->willReturn('TestProcessor');

        // Should not throw exception when Sentry classes don't exist
        $this->subscriber->startAnonymousTransaction($event);

        $this->assertTrue(true); // No exception thrown
    }

    public function testSuccessTransactionWithoutSentry(): void
    {
        // Should not throw exception when Sentry classes don't exist
        $this->subscriber->successTransaction();

        $this->assertTrue(true); // No exception thrown
    }

    public function testErrorTransactionWithoutSentry(): void
    {
        // Should not throw exception when Sentry classes don't exist
        $this->subscriber->errorTransaction();

        $this->assertTrue(true); // No exception thrown
    }

    public function testMultipleStartTransactionCalls(): void
    {
        $event1 = $this->createMock(BatchConsumeEvent::class);
        $event1->method('getProcessorClass')->willReturn('TestProcessor1');
        $event1->method('getMessagesBatch')->willReturn(['msg1']);

        $event2 = $this->createMock(BatchConsumeEvent::class);
        $event2->method('getProcessorClass')->willReturn('TestProcessor2');
        $event2->method('getMessagesBatch')->willReturn(['msg2']);

        // Multiple calls should not cause issues
        $this->subscriber->startTransaction($event1);
        $this->subscriber->startTransaction($event2);

        $this->assertTrue(true); // No exception thrown
    }

    public function testMultipleStartAnonymousTransactionCalls(): void
    {
        $event1 = $this->createMock(PreConsumeEvent::class);
        $event1->method('getProcessorClass')->willReturn('TestProcessor1');

        $event2 = $this->createMock(PreConsumeEvent::class);
        $event2->method('getProcessorClass')->willReturn('TestProcessor2');

        // Multiple calls should not cause issues
        $this->subscriber->startAnonymousTransaction($event1);
        $this->subscriber->startAnonymousTransaction($event2);

        $this->assertTrue(true); // No exception thrown
    }

    public function testSuccessTransactionWithoutActiveTransaction(): void
    {
        // Should handle case when no transaction is active
        $this->subscriber->successTransaction();

        $this->assertTrue(true); // No exception thrown
    }

    public function testErrorTransactionWithoutActiveTransaction(): void
    {
        // Should handle case when no transaction is active
        $this->subscriber->errorTransaction();

        $this->assertTrue(true); // No exception thrown
    }

    public function testTransactionLifecycle(): void
    {
        $batchEvent = $this->createMock(BatchConsumeEvent::class);
        $batchEvent->method('getProcessorClass')->willReturn('BatchProcessor');
        $batchEvent->method('getMessagesBatch')->willReturn(['msg1', 'msg2', 'msg3']);

        // Start transaction
        $this->subscriber->startTransaction($batchEvent);

        // Success transaction (should finish and reset)
        $this->subscriber->successTransaction();

        // Should be able to start new transaction after finishing
        $this->subscriber->startTransaction($batchEvent);

        $this->assertTrue(true); // No exception thrown
    }

    public function testAnonymousTransactionLifecycle(): void
    {
        $preConsumeEvent = $this->createMock(PreConsumeEvent::class);
        $preConsumeEvent->method('getProcessorClass')->willReturn('SingleProcessor');

        // Start anonymous transaction
        $this->subscriber->startAnonymousTransaction($preConsumeEvent);

        // Error transaction (should finish and reset)
        $this->subscriber->errorTransaction();

        // Should be able to start new transaction after finishing
        $this->subscriber->startAnonymousTransaction($preConsumeEvent);

        $this->assertTrue(true); // No exception thrown
    }

    public function testMixedTransactionTypes(): void
    {
        $batchEvent = $this->createMock(BatchConsumeEvent::class);
        $batchEvent->method('getProcessorClass')->willReturn('BatchProcessor');
        $batchEvent->method('getMessagesBatch')->willReturn(['msg1']);

        $preConsumeEvent = $this->createMock(PreConsumeEvent::class);
        $preConsumeEvent->method('getProcessorClass')->willReturn('SingleProcessor');

        // Start batch transaction
        $this->subscriber->startTransaction($batchEvent);

        // Try to start anonymous transaction (should not override)
        $this->subscriber->startAnonymousTransaction($preConsumeEvent);

        // Finish with success
        $this->subscriber->successTransaction();

        $this->assertTrue(true); // No exception thrown
    }
}
