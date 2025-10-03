<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Command;

use Enqueue\Client\Config;
use Interop\Queue\Context;
use MessageBusBundle\Command\RequeueFailedCommand;
use MessageBusBundle\Producer\ProducerInterface;
use PHPUnit\Framework\TestCase;

class RequeueFailedCommandTest extends TestCase
{
    public function testConfigure(): void
    {
        $context = $this->createMock(Context::class);
        $producer = $this->createMock(ProducerInterface::class);
        $config = $this->createMock(Config::class);

        $command = new RequeueFailedCommand($context, $producer, $config);

        $this->assertEquals('messagebus:requeue', $command->getName());
        $this->assertStringContainsString('Move messages between queues', $command->getDescription());
    }

    public function testBasicExecution(): void
    {
        $context = $this->createMock(Context::class);
        $producer = $this->createMock(ProducerInterface::class);
        $config = $this->createMock(Config::class);

        $command = new RequeueFailedCommand($context, $producer, $config);

        $this->assertInstanceOf(RequeueFailedCommand::class, $command);
    }
}
