<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Integration\AmqpTools;

use MessageBusBundle\AmqpTools\QueueType;
use MessageBusBundle\Tests\Stub\Processor\TestProcessor;
use MessageBusBundle\Tests\Stub\Processor\TestQuorumProcessor;
use PHPUnit\Framework\TestCase;

class QueueTypeIntegrationTest extends TestCase
{
    public function testDefaultProcessorReturnsDefaultQueueType(): void
    {
        $processor = new TestProcessor();
        $this->assertEquals(QueueType::DEFAULT, $processor->getQueueType());
    }

    public function testQuorumProcessorReturnsQuorumQueueType(): void
    {
        $processor = new TestQuorumProcessor();
        $this->assertEquals(QueueType::QUORUM, $processor->getQueueType());
    }

    public function testQueueTypeEnumValues(): void
    {
        $this->assertEquals('default', QueueType::DEFAULT->value);
        $this->assertEquals('quorum', QueueType::QUORUM->value);
    }
}
