<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EnqueueProcessor\Batch;

use Interop\Amqp\AmqpMessage;
use MessageBusBundle\EnqueueProcessor\Batch\BatchContext;
use MessageBusBundle\Exception\RequeueException;
use PHPUnit\Framework\TestCase;

class BatchTestAmqpMessage implements AmqpMessage
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

class BatchContextTest extends TestCase
{
    public function testGetBatchResultEmpty(): void
    {
        $context = new BatchContext();
        $result = $context->getBatchResult();

        $this->assertEquals([], $result);
    }

    public function testAck(): void
    {
        $context = new BatchContext();
        $message = new BatchTestAmqpMessage(123);

        $result = $context->ack($message);

        $this->assertSame($context, $result);
        $this->assertCount(1, $context->getBatchResult());
    }

    public function testReject(): void
    {
        $context = new BatchContext();
        $message = new BatchTestAmqpMessage(456);

        $result = $context->reject($message);

        $this->assertSame($context, $result);
        $this->assertCount(1, $context->getBatchResult());
    }

    public function testRequeue(): void
    {
        $context = new BatchContext();
        $message = new BatchTestAmqpMessage(789);
        $exception = new RequeueException('Test');

        $result = $context->requeue($message, $exception);

        $this->assertSame($context, $result);
        $this->assertCount(1, $context->getBatchResult());
    }

    public function testFinalClass(): void
    {
        $reflection = new \ReflectionClass(BatchContext::class);
        $this->assertTrue($reflection->isFinal());
    }
}
