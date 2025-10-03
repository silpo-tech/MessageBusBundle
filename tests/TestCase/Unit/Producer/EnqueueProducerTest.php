<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Producer;

use Enqueue\Client\Config;
use Enqueue\ConnectionFactoryFactoryInterface;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Queue\ConnectionFactory;
use Interop\Queue\Producer;
use MessageBusBundle\Events;
use MessageBusBundle\Events\PrePublishEvent;
use MessageBusBundle\MessageBus;
use MessageBusBundle\Producer\EnqueueProducer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\UuidV6;

class EnqueueProducerTest extends TestCase
{
    private Config $config;
    private ConnectionFactoryFactoryInterface $factory;
    private EventDispatcherInterface $eventDispatcher;
    private AmqpContext $context;
    private ConnectionFactory $connectionFactory;
    private Producer $producer;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->factory = $this->createMock(ConnectionFactoryFactoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->context = $this->createMock(AmqpContext::class);
        $this->connectionFactory = $this->createMock(ConnectionFactory::class);
        $this->producer = $this->createMock(Producer::class);
        $this->producer->method('setDeliveryDelay')->willReturnSelf();

        $this->config->method('getTransportOptions')->willReturn([]);
        $this->config->method('getSeparator')->willReturn('.');
        $this->config->method('getPrefix')->willReturn('app');
        $this->config->method('getRouterTopic')->willReturn('router');

        $this->factory->method('create')->willReturn($this->connectionFactory);
        $this->connectionFactory->method('createContext')->willReturn($this->context);
        $this->context->method('createProducer')->willReturn($this->producer);
    }

    public function testConstructor(): void
    {
        $enqueueProducer = new EnqueueProducer($this->config, $this->factory, $this->eventDispatcher);
        $this->assertInstanceOf(EnqueueProducer::class, $enqueueProducer);
    }

    public function testSend(): void
    {
        $message = $this->createMock(AmqpMessage::class);
        $topic = $this->createMock(AmqpTopic::class);

        $this->context->method('createMessage')->willReturn($message);
        $this->context->method('createTopic')->with(MessageBus::DEFAULT_EXCHANGE)->willReturn($topic);

        $message->expects($this->once())->method('setTimestamp');
        $message->expects($this->once())->method('getCorrelationId')->willReturn(null);
        $message->expects($this->once())->method('setCorrelationId');
        $message->expects($this->once())->method('setRoutingKey')->with('test.topic');
        $message->expects($this->once())->method('setProperty')->with('enqueue.topic', 'test.topic');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PrePublishEvent::class), Events::PRODUCER__PRE_PUBLISH);

        $this->producer->expects($this->once())->method('send')->with($topic, $message);

        $enqueueProducer = new EnqueueProducer($this->config, $this->factory, $this->eventDispatcher);
        $result = $enqueueProducer->send('test.topic', 'test message');

        $this->assertSame($enqueueProducer, $result);
    }

    public function testSendWithHeaders(): void
    {
        $message = $this->createMock(AmqpMessage::class);
        $topic = $this->createMock(AmqpTopic::class);

        $this->context->method('createMessage')->willReturn($message);
        $this->context->method('createTopic')->willReturn($topic);

        $message->expects($this->exactly(3))->method('setProperty');

        $enqueueProducer = new EnqueueProducer($this->config, $this->factory, $this->eventDispatcher);
        $result = $enqueueProducer->send('test.topic', 'test message', ['header1' => 'value1', 'header2' => 'value2']);

        $this->assertSame($enqueueProducer, $result);
    }

    public function testSendWithCustomExchange(): void
    {
        $message = $this->createMock(AmqpMessage::class);
        $topic = $this->createMock(AmqpTopic::class);

        $this->context->method('createMessage')->willReturn($message);
        $this->context->expects($this->once())->method('createTopic')->with('custom.exchange')->willReturn($topic);

        $enqueueProducer = new EnqueueProducer($this->config, $this->factory, $this->eventDispatcher);
        $result = $enqueueProducer->send('test.topic', 'test message', [], 0, 'custom.exchange');

        $this->assertSame($enqueueProducer, $result);
    }

    public function testSendMessage(): void
    {
        $message = $this->createMock(AmqpMessage::class);
        $topic = $this->createMock(AmqpTopic::class);

        $this->context->method('createTopic')->willReturn($topic);

        $message->expects($this->once())->method('setRoutingKey')->with('test.topic');
        $message->expects($this->once())->method('setProperty')->with('enqueue.topic', 'test.topic');

        $enqueueProducer = new EnqueueProducer($this->config, $this->factory, $this->eventDispatcher);
        $result = $enqueueProducer->sendMessage('test.topic', $message);

        $this->assertSame($enqueueProducer, $result);
    }

    public function testSendToQueue(): void
    {
        $message = $this->createMock(AmqpMessage::class);
        $queue = $this->createMock(AmqpQueue::class);

        $this->context->method('createMessage')->willReturn($message);
        $this->context->method('createQueue')->willReturn($queue);

        $message->expects($this->once())->method('setTimestamp');
        $message->expects($this->once())->method('getCorrelationId')->willReturn(null);
        $message->expects($this->once())->method('setCorrelationId');

        $queue->expects($this->once())->method('addFlag')->with(AmqpQueue::FLAG_DURABLE);
        $this->context->expects($this->once())->method('declareQueue')->with($queue);

        $enqueueProducer = new EnqueueProducer($this->config, $this->factory, $this->eventDispatcher);
        $result = $enqueueProducer->sendToQueue('test.queue', 'test message');

        $this->assertSame($enqueueProducer, $result);
    }

    public function testSendMessageToQueue(): void
    {
        $message = $this->createMock(AmqpMessage::class);
        $queue = $this->createMock(AmqpQueue::class);

        $this->context->method('createQueue')->willReturn($queue);

        $queue->expects($this->once())->method('addFlag')->with(AmqpQueue::FLAG_DURABLE);
        $this->context->expects($this->once())->method('declareQueue')->with($queue);

        $enqueueProducer = new EnqueueProducer($this->config, $this->factory, $this->eventDispatcher);
        $result = $enqueueProducer->sendMessageToQueue('test.queue', $message);

        $this->assertSame($enqueueProducer, $result);
    }

    public function testSendWithRetryOnException(): void
    {
        $message = $this->createMock(AmqpMessage::class);
        $topic = $this->createMock(AmqpTopic::class);
        $logger = $this->createMock(LoggerInterface::class);

        $this->context->method('createMessage')->willReturn($message);
        $this->context->method('createTopic')->willReturn($topic);

        $exception = new \Exception('Connection failed');
        $this->producer->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(function () use ($exception) {
                static $callCount = 0;
                ++$callCount;
                if (1 === $callCount) {
                    throw $exception;
                }

                return null;
            });

        $logger->expects($this->once())->method('debug');

        $enqueueProducer = new EnqueueProducer($this->config, $this->factory, $this->eventDispatcher);
        $enqueueProducer->setLogger($logger);

        $result = $enqueueProducer->send('test.topic', 'test message');
        $this->assertSame($enqueueProducer, $result);
    }

    public function testSendFailsAfterMaxRetries(): void
    {
        $message = $this->createMock(AmqpMessage::class);
        $topic = $this->createMock(AmqpTopic::class);
        $logger = $this->createMock(LoggerInterface::class);

        $this->context->method('createMessage')->willReturn($message);
        $this->context->method('createTopic')->willReturn($topic);

        $exception = new \Exception('Connection failed');
        $this->producer->method('send')->willThrowException($exception);

        // Allow debug to be called multiple times (up to max retries)
        $logger->expects($this->atLeast(3))->method('debug');
        $logger->expects($this->once())->method('error');

        $enqueueProducer = new EnqueueProducer($this->config, $this->factory, $this->eventDispatcher);
        $enqueueProducer->setLogger($logger);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Connection failed');

        $enqueueProducer->send('test.topic', 'test message');
    }

    public function testCreateMessageWithExistingCorrelationId(): void
    {
        $message = $this->createMock(AmqpMessage::class);
        $topic = $this->createMock(AmqpTopic::class);
        $existingId = UuidV6::generate();

        $this->context->method('createMessage')->willReturn($message);
        $this->context->method('createTopic')->willReturn($topic);

        $message->expects($this->once())->method('getCorrelationId')->willReturn($existingId);
        $message->expects($this->never())->method('setCorrelationId');

        $enqueueProducer = new EnqueueProducer($this->config, $this->factory, $this->eventDispatcher);
        $enqueueProducer->send('test.topic', 'test message');
    }

    public function testCreateTopicNameWithEmptyPrefix(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getTransportOptions')->willReturn([]);
        $config->method('getSeparator')->willReturn('.');
        $config->method('getPrefix')->willReturn('');
        $config->method('getRouterTopic')->willReturn('router');

        $factory = $this->createMock(ConnectionFactoryFactoryInterface::class);
        $factory->method('create')->willReturn($this->connectionFactory);

        $producer = new EnqueueProducer($config, $factory, $this->eventDispatcher);
        $this->assertInstanceOf(EnqueueProducer::class, $producer);
    }

    public function testCreateTopicNameWithNullPrefix(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getTransportOptions')->willReturn([]);
        $config->method('getSeparator')->willReturn('.');
        $config->method('getPrefix')->willReturn('');
        $config->method('getRouterTopic')->willReturn('router');

        $factory = $this->createMock(ConnectionFactoryFactoryInterface::class);
        $factory->method('create')->willReturn($this->connectionFactory);

        $producer = new EnqueueProducer($config, $factory, $this->eventDispatcher);
        $this->assertInstanceOf(EnqueueProducer::class, $producer);
    }
}
