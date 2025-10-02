<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EnqueueProcessor\Batch;

use Enqueue\Consumption\Result as EnqueueResult;
use MessageBusBundle\EnqueueProcessor\Batch\Result;
use PHPUnit\Framework\TestCase;

class ResultTest extends TestCase
{
    public function testAck(): void
    {
        $result = Result::ack(123);

        $this->assertEquals(123, $result->getDeliveryTag());
        $this->assertEquals(EnqueueResult::ACK, $result->getOpResult());
        $this->assertNull($result->getException());
    }

    public function testReject(): void
    {
        $result = Result::reject(456);

        $this->assertEquals(456, $result->getDeliveryTag());
        $this->assertEquals(EnqueueResult::REJECT, $result->getOpResult());
        $this->assertNull($result->getException());
    }

    public function testRequeue(): void
    {
        $exception = new \Exception('Test exception');
        $result = Result::requeue(789, $exception);

        $this->assertEquals(789, $result->getDeliveryTag());
        $this->assertEquals(EnqueueResult::REQUEUE, $result->getOpResult());
        $this->assertSame($exception, $result->getException());
    }
}
