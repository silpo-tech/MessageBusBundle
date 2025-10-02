<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Consumption;

use Interop\Queue\Context;
use Interop\Queue\Message;
use MessageBusBundle\Consumption\BatchCallbackProcessor;
use PHPUnit\Framework\TestCase;

class BatchCallbackProcessorTest extends TestCase
{
    public function testProcess(): void
    {
        $message1 = $this->createMock(Message::class);
        $message2 = $this->createMock(Message::class);
        $context = $this->createMock(Context::class);
        $messages = [$message1, $message2];

        $callback = function (array $messagesBatch, Context $ctx) use ($messages, $context) {
            $this->assertSame($messages, $messagesBatch);
            $this->assertSame($context, $ctx);

            return ['result1', 'result2'];
        };

        $processor = new BatchCallbackProcessor($callback);
        $result = $processor->process($messages, $context);

        $this->assertEquals(['result1', 'result2'], $result);
    }
}
