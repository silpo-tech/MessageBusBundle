<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Enqueue;

use Enqueue\Consumption\Context\MessageReceived;
use Enqueue\Consumption\Context\PostMessageReceived;
use Enqueue\Consumption\Result;
use Enqueue\Util\Stringify;
use Interop\Queue\Consumer;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use Interop\Queue\Queue;
use MessageBusBundle\Enqueue\LogExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LogExtensionTest extends TestCase
{
    private LoggerInterface|MockObject $logger;
    private Context|MockObject $context;
    private Consumer|MockObject $consumer;
    private Queue|MockObject $queue;
    private Message|MockObject $message;
    private Processor|MockObject $processor;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->context = $this->createMock(Context::class);
        $this->consumer = $this->createMock(Consumer::class);
        $this->queue = $this->createMock(Queue::class);
        $this->message = $this->createMock(Message::class);
        $this->processor = $this->createMock(Processor::class);

        $this->consumer->method('getQueue')->willReturn($this->queue);
        $this->queue->method('getQueueName')->willReturn('test.queue');
    }

    public function testOnMessageReceivedWithDebugDisabled(): void
    {
        $extension = new LogExtension(false);
        $messageContext = new MessageReceived(
            $this->context,
            $this->consumer,
            $this->message,
            $this->processor,
            time(),
            $this->logger
        );

        $this->logger->expects($this->never())->method('debug');

        $extension->onMessageReceived($messageContext);
    }

    public function testOnMessageReceivedWithDebugEnabled(): void
    {
        $extension = new LogExtension(true);

        $this->message->method('isRedelivered')->willReturn(true);
        $this->message->method('getBody')->willReturn('test body');
        $this->message->method('getProperties')->willReturn(['prop' => 'value']);
        $this->message->method('getHeaders')->willReturn(['header' => 'value']);

        $messageContext = new MessageReceived(
            $this->context,
            $this->consumer,
            $this->message,
            $this->processor,
            time(),
            $this->logger
        );

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                '[messagebus] received from {queueName}	{body}',
                $this->callback(function (array $logContext) {
                    return 'test.queue' === $logContext['queueName']
                        && true === $logContext['redelivered']
                        && $logContext['body'] instanceof Stringify
                        && $logContext['properties'] instanceof Stringify
                        && $logContext['headers'] instanceof Stringify;
                })
            );

        $extension->onMessageReceived($messageContext);
    }

    public function testOnPostMessageReceivedWithDebugDisabled(): void
    {
        $extension = new LogExtension(false);
        $postContext = new PostMessageReceived(
            $this->context,
            $this->consumer,
            $this->message,
            'ack',
            time(),
            $this->logger
        );

        $this->logger->expects($this->never())->method('debug');

        $extension->onPostMessageReceived($postContext);
    }

    public function testOnPostMessageReceivedWithDebugEnabledAndSimpleResult(): void
    {
        $extension = new LogExtension(true);

        $this->message->method('getBody')->willReturn('test body');
        $this->message->method('getProperties')->willReturn(['prop' => 'value']);
        $this->message->method('getHeaders')->willReturn(['header' => 'value']);

        $postContext = new PostMessageReceived(
            $this->context,
            $this->consumer,
            $this->message,
            'enqueue.ack',
            time(),
            $this->logger
        );

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                '[messagebus] processed from {queueName}	{body}	{result}',
                $this->callback(function (array $logContext) {
                    return 'ack' === $logContext['result']
                        && '' === $logContext['reason']
                        && 'test.queue' === $logContext['queueName']
                        && $logContext['body'] instanceof Stringify
                        && $logContext['properties'] instanceof Stringify
                        && $logContext['headers'] instanceof Stringify;
                })
            );

        $extension->onPostMessageReceived($postContext);
    }

    public function testOnPostMessageReceivedWithDebugEnabledAndResultWithReason(): void
    {
        $extension = new LogExtension(true);
        $result = $this->createMock(Result::class);

        $this->message->method('getBody')->willReturn('test body');
        $this->message->method('getProperties')->willReturn([]);
        $this->message->method('getHeaders')->willReturn([]);

        $result->method('getReason')->willReturn('Test reason');
        $result->method('__toString')->willReturn('reject');

        $postContext = new PostMessageReceived(
            $this->context,
            $this->consumer,
            $this->message,
            $result,
            time(),
            $this->logger
        );

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                '[messagebus] processed from {queueName}	{body}	{result} {reason}',
                $this->callback(function (array $logContext) {
                    return 'Test reason' === $logContext['reason']
                        && 'reject' === $logContext['result'];
                })
            );

        $extension->onPostMessageReceived($postContext);
    }

    public function testOnPostMessageReceivedWithDebugEnabledAndResultWithoutReason(): void
    {
        $extension = new LogExtension(true);
        $result = $this->createMock(Result::class);

        $this->message->method('getBody')->willReturn('test body');
        $this->message->method('getProperties')->willReturn([]);
        $this->message->method('getHeaders')->willReturn([]);

        $result->method('getReason')->willReturn(null);
        $result->method('__toString')->willReturn('ack');

        $postContext = new PostMessageReceived(
            $this->context,
            $this->consumer,
            $this->message,
            $result,
            time(),
            $this->logger
        );

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                '[messagebus] processed from {queueName}	{body}	{result}',
                $this->callback(function (array $logContext) {
                    return '' === $logContext['reason']
                        && 'ack' === $logContext['result'];
                })
            );

        $extension->onPostMessageReceived($postContext);
    }
}
