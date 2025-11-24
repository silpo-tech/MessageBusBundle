<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\AmqpTools;

use MessageBusBundle\AmqpTools\QueueType;
use PHPUnit\Framework\TestCase;

class QueueTypeTest extends TestCase
{
    public function testDefaultCase(): void
    {
        $this->assertEquals('default', QueueType::DEFAULT->value);
    }

    public function testQuorumCase(): void
    {
        $this->assertEquals('quorum', QueueType::QUORUM->value);
    }

    public function testFromString(): void
    {
        $this->assertSame(QueueType::DEFAULT, QueueType::from('default'));
        $this->assertSame(QueueType::QUORUM, QueueType::from('quorum'));
    }

    public function testAllCases(): void
    {
        $cases = QueueType::cases();
        $this->assertCount(2, $cases);
        $this->assertContains(QueueType::DEFAULT, $cases);
        $this->assertContains(QueueType::QUORUM, $cases);
    }
}
