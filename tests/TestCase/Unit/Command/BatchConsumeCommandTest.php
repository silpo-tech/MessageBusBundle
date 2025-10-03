<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Command;

use MessageBusBundle\AmqpTools\RabbitMqQueueManager;
use MessageBusBundle\Command\BatchConsumeCommand;
use MessageBusBundle\Consumption\BatchQueueConsumerInterface;
use MessageBusBundle\Enqueue\BatchProcessorRegistryInterface;
use MessageBusBundle\EnqueueProcessor\Batch\BatchProcessorInterface;
use MessageBusBundle\Exception\NonBatchProcessorException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BatchConsumeCommandTest extends TestCase
{
    public function testConfigure(): void
    {
        $consumer = $this->createMock(BatchQueueConsumerInterface::class);
        $registry = $this->createMock(BatchProcessorRegistryInterface::class);
        $queueManager = $this->createMock(RabbitMqQueueManager::class);

        $command = new BatchConsumeCommand($consumer, $registry, $queueManager);

        $this->assertEquals('messagebus:batch:consume', $command->getName());
        $this->assertStringContainsString('Consume messages in batches', $command->getDescription());
    }

    public function testExecute(): void
    {
        $consumer = $this->createMock(BatchQueueConsumerInterface::class);
        $registry = $this->createMock(BatchProcessorRegistryInterface::class);
        $queueManager = $this->createMock(RabbitMqQueueManager::class);
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        // Create a mock processor that implements BatchProcessorInterface but is NOT an AbstractBatchProcessor
        $processor = $this->createMock(BatchProcessorInterface::class);

        $input->method('getArgument')->with('processor')->willReturn('test-processor');
        $input->method('getOption')->willReturnMap([
            ['memory-limit', 100],
            ['message-limit', 0],
            ['time-limit', 0],
            ['setup-broker', false],
            ['idle-timeout', 0],
            ['receive-timeout', 10000],
            ['batch-size', 10],
        ]);

        $registry->expects($this->once())
            ->method('get')
            ->with('test-processor')
            ->willReturn($processor);

        $this->expectException(NonBatchProcessorException::class);
        $this->expectExceptionMessage('test-processor processor is not support batch consume');

        $command = new BatchConsumeCommand($consumer, $registry, $queueManager);
        $command->run($input, $output);
    }
}
