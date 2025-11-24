<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Command;

use MessageBusBundle\AmqpTools\QueueType;
use MessageBusBundle\AmqpTools\RabbitMqQueueManager;
use MessageBusBundle\Command\SetupTransportCommand;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SetupTransportCommandQueueTypeTest extends TestCase
{
    private RabbitMqQueueManager|MockObject $queueManager;
    private SetupTransportCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->queueManager = $this->createMock(RabbitMqQueueManager::class);
    }

    public function testProcessorWithDefaultQueueType(): void
    {
        $processor = $this->createMock(ProcessorInterface::class);
        $processor->method('getSubscribedRoutingKeys')->willReturn([
            'default.queue' => ['routing.key'],
        ]);
        $processor->method('getQueueType')->willReturn(QueueType::DEFAULT);

        $this->command = new SetupTransportCommand($this->queueManager, [$processor]);

        $application = new Application();
        $application->add($this->command);
        $this->commandTester = new CommandTester($this->command);

        $this->queueManager->expects($this->once())
            ->method('initQueue')
            ->with('default.queue', ['routing.key'], QueueType::DEFAULT);

        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
    }

    public function testProcessorWithQuorumQueueType(): void
    {
        $processor = $this->createMock(ProcessorInterface::class);
        $processor->method('getSubscribedRoutingKeys')->willReturn([
            'quorum.queue' => ['routing.key'],
        ]);
        $processor->method('getQueueType')->willReturn(QueueType::QUORUM);

        $this->command = new SetupTransportCommand($this->queueManager, [$processor]);

        $application = new Application();
        $application->add($this->command);
        $this->commandTester = new CommandTester($this->command);

        $this->queueManager->expects($this->once())
            ->method('initQueue')
            ->with('quorum.queue', ['routing.key'], QueueType::QUORUM);

        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
    }

    public function testMultipleProcessorsWithDifferentQueueTypes(): void
    {
        $defaultProcessor = $this->createMock(ProcessorInterface::class);
        $defaultProcessor->method('getSubscribedRoutingKeys')->willReturn([
            'default.queue' => ['default.routing'],
        ]);
        $defaultProcessor->method('getQueueType')->willReturn(QueueType::DEFAULT);

        $quorumProcessor = $this->createMock(ProcessorInterface::class);
        $quorumProcessor->method('getSubscribedRoutingKeys')->willReturn([
            'quorum.queue' => ['quorum.routing'],
        ]);
        $quorumProcessor->method('getQueueType')->willReturn(QueueType::QUORUM);

        $this->command = new SetupTransportCommand(
            $this->queueManager,
            [$defaultProcessor, $quorumProcessor]
        );

        $application = new Application();
        $application->add($this->command);
        $this->commandTester = new CommandTester($this->command);

        $this->queueManager->expects($this->exactly(2))
            ->method('initQueue')
            ->willReturnCallback(function ($queue, $routingKeys, $queueType) {
                if ('default.queue' === $queue) {
                    $this->assertEquals(QueueType::DEFAULT, $queueType);
                    $this->assertEquals(['default.routing'], $routingKeys);
                } elseif ('quorum.queue' === $queue) {
                    $this->assertEquals(QueueType::QUORUM, $queueType);
                    $this->assertEquals(['quorum.routing'], $routingKeys);
                }
            });

        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
    }
}
