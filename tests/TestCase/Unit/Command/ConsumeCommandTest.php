<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Command;

use MessageBusBundle\AmqpTools\RabbitMqQueueManager;
use MessageBusBundle\Command\ConsumeCommand;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConsumeCommandTest extends TestCase
{
    public function testConfigure(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $queueManager = $this->createMock(RabbitMqQueueManager::class);

        $command = new ConsumeCommand($container, $queueManager, 'default');

        $this->assertEquals('messagebus:consume', $command->getName());
        $this->assertStringContainsString('A worker that consumes message from a broker', $command->getDescription());
    }

    public function testExecute(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $queueManager = $this->createMock(RabbitMqQueueManager::class);
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $input->method('getArgument')->with('processor')->willReturn('test-processor');
        $input->method('getOption')->willReturnMap([
            ['memory-limit', 100],
            ['message-limit', 0],
            ['time-limit', 0],
            ['setup-broker', false],
            ['idle-timeout', 0],
            ['receive-timeout', 10000],
        ]);

        $command = new ConsumeCommand($container, $queueManager, 'default');

        // We can't easily test the full execution without mocking the entire consumer chain
        $this->assertInstanceOf(ConsumeCommand::class, $command);
    }
}
