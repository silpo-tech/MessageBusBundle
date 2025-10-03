<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EnqueueProcessor\Batch;

use Interop\Amqp\AmqpMessage;
use MessageBusBundle\EnqueueProcessor\Batch\AbstractBatchContext;
use MessageBusBundle\EnqueueProcessor\Batch\Result;
use MessageBusBundle\Exception\RequeueException;
use PHPUnit\Framework\TestCase;

class TestAmqpMessage implements AmqpMessage
{
    private ?int $deliveryTag;

    public function __construct(?int $deliveryTag)
    {
        $this->deliveryTag = $deliveryTag;
    }

    public function getDeliveryTag(): ?int
    {
        return $this->deliveryTag;
    }

    public function getBody(): string
    {
        return '';
    }

    public function setBody(string $body): void
    {
    }

    public function getProperties(): array
    {
        return [];
    }

    public function setProperties(array $properties): void
    {
    }

    public function getProperty(string $name, $default = null)
    {
        return $default;
    }

    public function setProperty(string $name, $value): void
    {
    }

    public function getHeaders(): array
    {
        return [];
    }

    public function setHeaders(array $headers): void
    {
    }

    public function getHeader(string $name, $default = null)
    {
        return $default;
    }

    public function setHeader(string $name, $value): void
    {
    }

    public function isRedelivered(): bool
    {
        return false;
    }

    public function setRedelivered(bool $redelivered): void
    {
    }

    public function getCorrelationId(): ?string
    {
        return null;
    }

    public function setCorrelationId(?string $correlationId = null): void
    {
    }

    public function getMessageId(): ?string
    {
        return null;
    }

    public function setMessageId(?string $messageId = null): void
    {
    }

    public function getTimestamp(): ?int
    {
        return null;
    }

    public function setTimestamp(?int $timestamp = null): void
    {
    }

    public function getReplyTo(): ?string
    {
        return null;
    }

    public function setReplyTo(?string $replyTo = null): void
    {
    }

    public function getContentType(): ?string
    {
        return null;
    }

    public function setContentType(?string $contentType = null): void
    {
    }

    public function getContentEncoding(): ?string
    {
        return null;
    }

    public function setContentEncoding(?string $contentEncoding = null): void
    {
    }

    public function getPriority(): ?int
    {
        return null;
    }

    public function setPriority(?int $priority = null): void
    {
    }

    public function getExpiration(): ?int
    {
        return null;
    }

    public function setExpiration(?int $expiration = null): void
    {
    }

    public function getUserId(): ?string
    {
        return null;
    }

    public function setUserId(?string $userId = null): void
    {
    }

    public function getAppId(): ?string
    {
        return null;
    }

    public function setAppId(?string $appId = null): void
    {
    }

    public function getType(): ?string
    {
        return null;
    }

    public function setType(?string $type = null): void
    {
    }

    public function getRoutingKey(): ?string
    {
        return null;
    }

    public function setRoutingKey(?string $routingKey = null): void
    {
    }

    public function getDeliveryMode(): ?int
    {
        return null;
    }

    public function setDeliveryMode(?int $deliveryMode = null): void
    {
    }

    public function setDeliveryTag(?int $deliveryTag = null): void
    {
        $this->deliveryTag = $deliveryTag;
    }

    public function getClusterId(): ?string
    {
        return null;
    }

    public function setClusterId(?string $clusterId = null): void
    {
    }

    public function getExchange(): ?string
    {
        return null;
    }

    public function setExchange(?string $exchange = null): void
    {
    }

    public function getQueue(): ?string
    {
        return null;
    }

    public function setQueue(?string $queue = null): void
    {
    }

    public function getConsumerTag(): ?string
    {
        return null;
    }

    public function setConsumerTag(?string $consumerTag = null): void
    {
    }

    public function clearFlags(): void
    {
    }

    public function addFlag(int $flag): void
    {
    }

    public function getFlags(): int
    {
        return 0;
    }

    public function clearFlag(int $flag): void
    {
    }

    public function setFlags(int $flags): void
    {
    }
}

class AbstractBatchContextTest extends TestCase
{
    private AbstractBatchContext $context;

    protected function setUp(): void
    {
        $this->context = new class extends AbstractBatchContext {};
    }

    public function testGetBatchResultEmpty(): void
    {
        $result = $this->context->getBatchResult();

        $this->assertEquals([], $result);
    }

    public function testAck(): void
    {
        $message = new TestAmqpMessage(123);

        $result = $this->context->ack($message);

        $this->assertSame($this->context, $result);

        $batchResult = $this->context->getBatchResult();
        $this->assertCount(1, $batchResult);
        $this->assertArrayHasKey(123, $batchResult);

        $resultObject = $batchResult[123];
        $this->assertInstanceOf(Result::class, $resultObject);
    }

    public function testReject(): void
    {
        $message = new TestAmqpMessage(456);

        $result = $this->context->reject($message);

        $this->assertSame($this->context, $result);

        $batchResult = $this->context->getBatchResult();
        $this->assertCount(1, $batchResult);
        $this->assertArrayHasKey(456, $batchResult);

        $resultObject = $batchResult[456];
        $this->assertInstanceOf(Result::class, $resultObject);
    }

    public function testRequeue(): void
    {
        $message = new TestAmqpMessage(789);
        $exception = new RequeueException('Requeue test');

        $result = $this->context->requeue($message, $exception);

        $this->assertSame($this->context, $result);

        $batchResult = $this->context->getBatchResult();
        $this->assertCount(1, $batchResult);
        $this->assertArrayHasKey(789, $batchResult);

        $resultObject = $batchResult[789];
        $this->assertInstanceOf(Result::class, $resultObject);
    }

    public function testProtectedAckMessage(): void
    {
        $message = new TestAmqpMessage(100);

        $testContext = new class extends AbstractBatchContext {
            public function testAckMessage(AmqpMessage $message): Result
            {
                return $this->ackMessage($message);
            }
        };

        $result = $testContext->testAckMessage($message);

        $this->assertInstanceOf(Result::class, $result);
    }

    public function testProtectedRejectMessage(): void
    {
        $message = new TestAmqpMessage(200);

        $testContext = new class extends AbstractBatchContext {
            public function testRejectMessage(AmqpMessage $message): Result
            {
                return $this->rejectMessage($message);
            }
        };

        $result = $testContext->testRejectMessage($message);

        $this->assertInstanceOf(Result::class, $result);
    }

    public function testProtectedRequeueMessage(): void
    {
        $message = new TestAmqpMessage(300);
        $exception = new RequeueException('Test requeue');

        $testContext = new class extends AbstractBatchContext {
            public function testRequeueMessage(AmqpMessage $message, RequeueException $exception): Result
            {
                return $this->requeueMessage($message, $exception);
            }
        };

        $result = $testContext->testRequeueMessage($message, $exception);

        $this->assertInstanceOf(Result::class, $result);
    }

    public function testMultipleOperations(): void
    {
        $message1 = new TestAmqpMessage(1);
        $message2 = new TestAmqpMessage(2);
        $message3 = new TestAmqpMessage(3);

        $exception = new RequeueException('Test requeue');

        $this->context
            ->ack($message1)
            ->reject($message2)
            ->requeue($message3, $exception);

        $batchResult = $this->context->getBatchResult();

        $this->assertCount(3, $batchResult);
        $this->assertArrayHasKey(1, $batchResult);
        $this->assertArrayHasKey(2, $batchResult);
        $this->assertArrayHasKey(3, $batchResult);
    }

    public function testOverwriteResult(): void
    {
        $message = new TestAmqpMessage(123);

        $this->context->ack($message);
        $this->context->reject($message);

        $batchResult = $this->context->getBatchResult();

        $this->assertCount(1, $batchResult);
        $this->assertArrayHasKey(123, $batchResult);
    }

    public function testFluentInterface(): void
    {
        $message1 = new TestAmqpMessage(1);
        $message2 = new TestAmqpMessage(2);

        $exception = new RequeueException('Fluent test');

        $result = $this->context
            ->ack($message1)
            ->reject($message2)
            ->requeue($message1, $exception);

        $this->assertSame($this->context, $result);
    }

    public function testAbstractClass(): void
    {
        $reflection = new \ReflectionClass(AbstractBatchContext::class);

        $this->assertTrue($reflection->isAbstract());
    }
}
