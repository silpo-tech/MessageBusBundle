<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Messenger;

use Interop\Amqp\Impl\AmqpMessage;
use Interop\Queue\Context;
use Interop\Queue\Processor;
use MapperBundle\Mapper\MapperInterface;
use MessageBusBundle\EnqueueProcessor\AbstractProcessor;
use MessageBusBundle\Events;
use MessageBusBundle\Exception\RejectException;
use MessageBusBundle\Exception\RequeueException;
use MessageBusBundle\Messenger\Bridge\EnqueueEnvelope;
use MessageBusBundle\Messenger\Bridge\EnqueueMessageFactory;
use MessageBusBundle\Messenger\Bridge\EnqueueProcessorBridgeHandler;
use MessageBusBundle\Messenger\Bridge\ProcessorRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class EnqueueProcessorBridgeHandlerTest extends TestCase
{
    private const PROCESSOR_ID = 'test.processor.send_push';

    private MockObject&AbstractProcessor $processor;
    private Context $context;
    private EventDispatcher $eventDispatcher;
    private MockObject&MapperInterface $mapper;
    private EnqueueProcessorBridgeHandler $handler;

    protected function setUp(): void
    {
        $this->processor = $this->createMock(AbstractProcessor::class);
        $this->context = $this->createMock(Context::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->mapper = $this->createMock(MapperInterface::class);

        $locator = new ServiceLocator([
            self::PROCESSOR_ID => fn (): AbstractProcessor => $this->processor,
        ]);

        $this->handler = new EnqueueProcessorBridgeHandler(
            new ProcessorRegistry($locator),
            new EnqueueMessageFactory(),
            $this->eventDispatcher,
            $this->mapper,
            $this->context,
        );
    }

    public function testItReplaysTheDecodedMessageIntoDoProcess(): void
    {
        // php.class_name points at a non-existent class, so the raw decoded body is passed through.
        $envelope = new EnqueueEnvelope(self::PROCESSOR_ID, '{"foo":"bar"}', ['php.class_name' => 'Foo', 'requeue-count' => 1]);

        $this->mapper->expects($this->never())->method('convert');

        $this->processor->expects($this->once())
            ->method('doProcess')
            ->with(
                ['foo' => 'bar'],
                self::callback(function (AmqpMessage $message): bool {
                    self::assertSame('{"foo":"bar"}', $message->getBody());
                    self::assertSame('Foo', $message->getProperty('php.class_name'));
                    self::assertSame(1, $message->getProperty('requeue-count'));

                    return true;
                }),
                $this->context,
            )
            ->willReturn(Processor::ACK);

        ($this->handler)($envelope);
    }

    public function testItMapsTheBodyWhenTheClassExists(): void
    {
        $dto = new \stdClass();
        $envelope = new EnqueueEnvelope(self::PROCESSOR_ID, '{"foo":"bar"}', ['php.class_name' => \stdClass::class]);

        $this->mapper->expects($this->once())
            ->method('convert')
            ->with(['foo' => 'bar'], \stdClass::class)
            ->willReturn($dto);

        $this->processor->expects($this->once())
            ->method('doProcess')
            ->with($dto, self::isInstanceOf(AmqpMessage::class), $this->context)
            ->willReturn(Processor::ACK);

        ($this->handler)($envelope);
    }

    public function testItDispatchesTheConsumeLifecycleEvents(): void
    {
        $seen = [];
        foreach ([Events::CONSUME__PRE_START, Events::CONSUME__START, Events::CONSUME__FINISHED] as $name) {
            $this->eventDispatcher->addListener($name, function () use ($name, &$seen): void {
                $seen[] = $name;
            });
        }

        $this->processor->method('doProcess')->willReturn(Processor::ACK);

        ($this->handler)(new EnqueueEnvelope(self::PROCESSOR_ID, '{}'));

        self::assertSame(
            [Events::CONSUME__PRE_START, Events::CONSUME__START, Events::CONSUME__FINISHED],
            $seen,
        );
    }

    public function testAckIsAcknowledgedWithoutThrowing(): void
    {
        $this->processor->method('doProcess')->willReturn(Processor::ACK);

        ($this->handler)(new EnqueueEnvelope(self::PROCESSOR_ID, '{}'));

        $this->addToAssertionCount(1);
    }

    public function testRejectStatusGoesToFailureTransport(): void
    {
        $this->processor->method('doProcess')->willReturn(Processor::REJECT);

        $this->expectException(UnrecoverableMessageHandlingException::class);

        ($this->handler)(new EnqueueEnvelope(self::PROCESSOR_ID, '{}'));
    }

    public function testRequeueStatusIsRetryable(): void
    {
        $this->processor->method('doProcess')->willReturn(Processor::REQUEUE);

        // An ordinary exception (not Recoverable...) so retry_strategy governs max_retries.
        $this->expectException(RequeueException::class);

        ($this->handler)(new EnqueueEnvelope(self::PROCESSOR_ID, '{}'));
    }

    public function testRejectExceptionGoesToFailureTransport(): void
    {
        $this->processor->method('doProcess')->willThrowException(new RejectException('nope'));

        $exceptionSeen = false;
        $this->eventDispatcher->addListener(Events::CONSUME__EXCEPTION, function () use (&$exceptionSeen): void {
            $exceptionSeen = true;
        });

        try {
            ($this->handler)(new EnqueueEnvelope(self::PROCESSOR_ID, '{}'));
            self::fail('Expected an exception.');
        } catch (UnrecoverableMessageHandlingException $e) {
            self::assertInstanceOf(RejectException::class, $e->getPrevious());
        }

        self::assertTrue($exceptionSeen, 'consume.exception must be dispatched.');
    }

    public function testRequeueExceptionIsRethrownAsIs(): void
    {
        $requeue = new RequeueException('retry me', 3);
        $this->processor->method('doProcess')->willThrowException($requeue);

        try {
            ($this->handler)(new EnqueueEnvelope(self::PROCESSOR_ID, '{}'));
            self::fail('Expected an exception.');
        } catch (RequeueException $e) {
            self::assertSame($requeue, $e);
        }
    }
}
