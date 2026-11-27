<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EnqueueProcessor\ExceptionHandler;

use Enqueue\Client\Config;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use MessageBusBundle\AmqpTools\QueueType;
use MessageBusBundle\EnqueueProcessor\ExceptionHandler\NonRetryableExceptionChecker;
use MessageBusBundle\EnqueueProcessor\ExceptionHandler\RequeueHandler;
use MessageBusBundle\EnqueueProcessor\OverridesRetryConfigInterface;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;
use MessageBusBundle\Events\ConsumeRequeueEvent;
use MessageBusBundle\Events\ConsumeRequeueExceedEvent;
use MessageBusBundle\Exception\RequeueException;
use MessageBusBundle\Producer\ProducerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

class RequeueHandlerTest extends TestCase
{
    private ProducerInterface&MockObject $producer;
    private Config&MockObject $config;
    private LoggerInterface&MockObject $logger;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private ProcessorInterface&MockObject $processor;

    protected function setUp(): void
    {
        $this->producer = $this->createMock(ProducerInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->config->method('getSeparator')->willReturn('.');
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->processor = $this->createMock(ProcessorInterface::class);
        $this->processor->method('getSubscribedRoutingKeys')->willReturn(['test.queue' => []]);
        $this->processor->method('getQueueType')->willReturn(QueueType::DEFAULT);
    }

    private function makeHandler(int $maxRetries = 0, bool $exposePrevious = false, array $nonRetryable = []): RequeueHandler
    {
        return new RequeueHandler(
            $this->producer,
            $this->config,
            $this->logger,
            $this->eventDispatcher,
            new NonRetryableExceptionChecker($nonRetryable),
            $maxRetries,
            $exposePrevious,
        );
    }

    // supports()

    #[DataProvider('providerNonRetryableExceptions')]
    public function testSupportsReturnsFalseForNonRetryableException(\Throwable $exception): void
    {
        self::assertFalse($this->makeHandler()->supports($exception));
    }

    public function testSupportsReturnsFalseWhenMaxRetriesIsZero(): void
    {
        self::assertFalse($this->makeHandler(maxRetries: 0)->supports(new \RuntimeException()));
    }

    public function testSupportsReturnsTrueForRetryableExceptionWithMaxRetries(): void
    {
        self::assertTrue($this->makeHandler(maxRetries: 3)->supports(new \RuntimeException()));
    }

    public function testSupportsReturnsTrueForRequeueException(): void
    {
        self::assertTrue($this->makeHandler(maxRetries: 0)->supports(new RequeueException('fail', 3)));
    }

    public function testSupportsReturnsFalseForRequeueExceptionWithZeroCount(): void
    {
        self::assertFalse($this->makeHandler(maxRetries: 5)->supports(new RequeueException('fail', 0)));
    }

    public function testSupportsReturnsFalseWhenProcessorOverridesMaxRetriesToZero(): void
    {
        $processor = new class implements ProcessorInterface, OverridesRetryConfigInterface {
            public function getMaxRetries(): int
            {
                return 0;
            }

            public function getSubscribedRoutingKeys(): array
            {
                return ['test.queue' => []];
            }

            public function getQueueType(): QueueType
            {
                return QueueType::DEFAULT;
            }
        };

        self::assertFalse($this->makeHandler(maxRetries: 5)->supports(new \RuntimeException(), $processor));
    }

    public function testSupportsReturnsTrueWhenProcessorOverridesMaxRetries(): void
    {
        $processor = new class implements ProcessorInterface, OverridesRetryConfigInterface {
            public function getMaxRetries(): int
            {
                return 3;
            }

            public function getSubscribedRoutingKeys(): array
            {
                return ['test.queue' => []];
            }

            public function getQueueType(): QueueType
            {
                return QueueType::DEFAULT;
            }
        };

        self::assertTrue($this->makeHandler(maxRetries: 0)->supports(new \RuntimeException(), $processor));
    }

    // handle() — retry

    public function testHandleRequeueWithinLimit(): void
    {
        $exception = new RequeueException('Test requeue', 3);
        $message = $this->createMock(Message::class);
        $context = $this->createMock(Context::class);

        $message->expects($this->once())->method('getProperty')->with('requeue-count', 0)->willReturn(1);
        $message->expects($this->exactly(2))->method('setProperty');
        $this->processor->expects($this->once())->method('getSubscribedRoutingKeys')->willReturn(['test.queue' => []]);
        $this->processor->expects($this->once())->method('getQueueType')->willReturn(QueueType::DEFAULT);

        $this->producer->expects($this->once())
            ->method('sendMessageToQueue')
            ->with('test.queue', $message, 4000, QueueType::DEFAULT);
        $this->eventDispatcher->expects($this->once())->method('dispatch')->with($this->isInstanceOf(ConsumeRequeueEvent::class));
        $this->logger->expects($this->once())->method('debug');

        $result = $this->makeHandler()->handle($exception, $message, $context, $this->processor);

        $this->assertEquals(Processor::ACK, $result);
    }

    public function testHandleRequeueExceedLimit(): void
    {
        $exception = new RequeueException('Test requeue', 2);
        $message = $this->createMock(Message::class);
        $context = $this->createMock(Context::class);

        $message->expects($this->once())->method('getProperty')->with('requeue-count', 0)->willReturn(2);
        $this->processor->expects($this->once())->method('getSubscribedRoutingKeys')->willReturn(['test.queue' => []]);
        $this->processor->expects($this->once())->method('getQueueType')->willReturn(QueueType::QUORUM);

        $this->producer->expects($this->once())
            ->method('sendMessageToQueue')
            ->with('test.queue.failed', $message, 0, QueueType::QUORUM);
        $this->eventDispatcher->expects($this->once())->method('dispatch')->with($this->isInstanceOf(ConsumeRequeueExceedEvent::class));
        $this->logger->expects($this->once())->method('error');

        $result = $this->makeHandler()->handle($exception, $message, $context, $this->processor);

        $this->assertEquals(Processor::ACK, $result);
    }

    public function testHandleUsesGlobalMaxRetriesForNonRequeueException(): void
    {
        $message = $this->createMock(Message::class);
        $message->method('getProperty')->with('requeue-count', 0)->willReturn(0);
        $message->method('setProperty');
        $message->method('getBody')->willReturn('{}');
        $message->method('getCorrelationId')->willReturn('id');

        $this->producer->expects($this->once())
            ->method('sendMessageToQueue')
            ->with('test.queue', $message, 2000, QueueType::DEFAULT);

        $this->makeHandler(maxRetries: 3)->handle(new \RuntimeException('fail'), $message, $this->createMock(Context::class), $this->processor);
    }

    // handle() — exposePreviousException

    public function testHandleUsesExceptionMessageWhenExposePreviousFalse(): void
    {
        $message = $this->createMock(Message::class);
        $message->method('getProperty')->willReturn(0);
        $message->method('getBody')->willReturn('{}');
        $message->method('getCorrelationId')->willReturn('id');

        $previous = new \RuntimeException('original message');
        $exception = new RequeueException('wrapper message', 3, $previous);

        $message->expects($this->exactly(2))->method('setProperty')->willReturnCallback(
            function (string $key, mixed $value) use (&$reason): void {
                if ('requeue-reason' === $key) {
                    $reason = $value;
                }
            }
        );

        $this->makeHandler(maxRetries: 3, exposePrevious: false)->handle($exception, $message, $this->createMock(Context::class), $this->processor);

        self::assertEquals('wrapper message', $reason);
    }

    public function testHandleUsesPreviousExceptionMessageWhenExposePreviousTrue(): void
    {
        $message = $this->createMock(Message::class);
        $message->method('getProperty')->willReturn(0);
        $message->method('getBody')->willReturn('{}');
        $message->method('getCorrelationId')->willReturn('id');

        $previous = new \RuntimeException('original message');
        $exception = new RequeueException('wrapper message', 3, $previous);

        $message->expects($this->exactly(2))->method('setProperty')->willReturnCallback(
            function (string $key, mixed $value) use (&$reason): void {
                if ('requeue-reason' === $key) {
                    $reason = $value;
                }
            }
        );

        $this->makeHandler(maxRetries: 3, exposePrevious: true)->handle($exception, $message, $this->createMock(Context::class), $this->processor);

        self::assertEquals('original message', $reason);
    }

    // BC

    public function testSupportsRequeueException(): void
    {
        self::assertTrue($this->makeHandler(maxRetries: 0)->supports(new RequeueException('Test')));
    }

    public function testDoesNotSupportOtherExceptionsWhenMaxRetriesIsZero(): void
    {
        self::assertFalse($this->makeHandler(maxRetries: 0)->supports(new \RuntimeException('Test')));
    }

    public static function providerNonRetryableExceptions(): iterable
    {
        yield 'InvalidArgumentException' => [new \InvalidArgumentException()];
        yield 'UnexpectedValueException' => [new \UnexpectedValueException()];
        yield 'TypeError' => [new \TypeError()];
        yield 'ValueError' => [new \ValueError()];
    }
}
