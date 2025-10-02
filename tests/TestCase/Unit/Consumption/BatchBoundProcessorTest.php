<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Consumption;

use Interop\Queue\Queue;
use MessageBusBundle\Consumption\BatchBoundProcessor;
use MessageBusBundle\EnqueueProcessor\Batch\BatchProcessorInterface;
use PHPUnit\Framework\TestCase;

class BatchBoundProcessorTest extends TestCase
{
    public function testGetters(): void
    {
        $queue = $this->createMock(Queue::class);
        $processor = $this->createMock(BatchProcessorInterface::class);

        $boundProcessor = new BatchBoundProcessor($queue, $processor);

        $this->assertSame($queue, $boundProcessor->getQueue());
        $this->assertSame($processor, $boundProcessor->getProcessor());
    }
}
