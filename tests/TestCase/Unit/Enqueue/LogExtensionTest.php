<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Enqueue;

use Enqueue\Consumption\Context\MessageReceived;
use Enqueue\Consumption\Context\PostMessageReceived;
use Enqueue\Consumption\Result;
use Enqueue\Null\NullContext;
use Enqueue\Null\NullMessage;
use Enqueue\Null\NullQueue;
use Interop\Queue\Processor;
use MessageBusBundle\Enqueue\LogExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LogExtensionTest extends TestCase
{
    #[DataProvider('debugModeProvider')]
    public function testOnMessageReceived(bool $debugEnabled, bool $expectsLog): void
    {
        $extension = new LogExtension($debugEnabled);
        $context = new NullContext();
        $queue = new NullQueue('test.queue');
        $consumer = $context->createConsumer($queue);
        $message = new NullMessage('test body', ['prop' => 'value'], ['header' => 'value']);
        $processor = $this->createMock(Processor::class);
        $logger = $this->createMock(LoggerInterface::class);

        $messageContext = new MessageReceived($context, $consumer, $message, $processor, time(), $logger);

        if ($expectsLog) {
            $logger->expects($this->once())
                ->method('debug')
                ->with(
                    '[messagebus] received from {queueName}	{body}',
                    $this->callback(function (array $context) {
                        return 'test.queue' === $context['queueName']
                            && false === $context['redelivered'];
                    })
                );
        } else {
            $logger->expects($this->never())->method('debug');
        }

        $extension->onMessageReceived($messageContext);
    }

    #[DataProvider('postMessageProvider')]
    public function testOnPostMessageReceived(bool $debugEnabled, $result, string $expectedResult, string $expectedReason): void
    {
        $extension = new LogExtension($debugEnabled);
        $context = new NullContext();
        $queue = new NullQueue('test.queue');
        $message = new NullMessage('test body');
        $consumer = $context->createConsumer($queue);
        $logger = $this->createMock(LoggerInterface::class);

        $postContext = new PostMessageReceived($context, $consumer, $message, $result, time(), $logger);

        if ($debugEnabled) {
            $logger->expects($this->once())
                ->method('debug')
                ->with(
                    $this->stringContains('[messagebus] processed from {queueName}'),
                    $this->callback(function (array $context) use ($expectedResult, $expectedReason) {
                        return $context['result'] === $expectedResult
                            && $context['reason'] === $expectedReason
                            && 'test.queue' === $context['queueName'];
                    })
                );
        } else {
            $logger->expects($this->never())->method('debug');
        }

        $extension->onPostMessageReceived($postContext);
    }

    public static function debugModeProvider(): array
    {
        return [
            'debug disabled' => [
                'debugEnabled' => false,
                'expectsLog' => false,
            ],
            'debug enabled' => [
                'debugEnabled' => true,
                'expectsLog' => true,
            ],
        ];
    }

    public static function postMessageProvider(): array
    {
        $resultWithReason = new Result(Result::REJECT, 'Test reason');
        $resultWithoutReason = new Result(Result::ACK);

        return [
            'debug disabled' => [
                'debugEnabled' => false,
                'result' => 'enqueue.ack',
                'expectedResult' => '',
                'expectedReason' => '',
            ],
            'debug enabled with string result' => [
                'debugEnabled' => true,
                'result' => 'enqueue.ack',
                'expectedResult' => 'ack',
                'expectedReason' => '',
            ],
            'debug enabled with result object with reason' => [
                'debugEnabled' => true,
                'result' => $resultWithReason,
                'expectedResult' => 'reject',
                'expectedReason' => 'Test reason',
            ],
            'debug enabled with result object without reason' => [
                'debugEnabled' => true,
                'result' => $resultWithoutReason,
                'expectedResult' => 'ack',
                'expectedReason' => '',
            ],
        ];
    }
}
