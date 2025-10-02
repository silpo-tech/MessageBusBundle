<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Integration\Command;

use Interop\Amqp\Impl\AmqpMessage;
use MessageBusBundle\Command\BatchConsumeCommand;
use MessageBusBundle\Producer\ProducerInterface;
use MessageBusBundle\Tests\Kernel;
use MessageBusBundle\Tests\Stub\Processor\TestBatchProcessor;
use PhpSolution\FunctionalTest\TestCase\ApiTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class BatchConsumeCommandTest extends ApiTestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $kernel = new Kernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();

        /** @var BatchConsumeCommand $command */
        $command = $container->get(BatchConsumeCommand::class);

        $application = new Application();
        $application->add($command);

        $this->commandTester = new CommandTester($command);
    }

    public function testCommandExecutesWithTestProcessor(): void
    {
        /** @var ProducerInterface $producer */
        $producer = self::getContainer()->get(ProducerInterface::class);
        $producer->sendMessageToQueue(TestBatchProcessor::QUEUE, new AmqpMessage('Test message'));

        // Expect the exception from TestBatchProcessor to prevent infinite consumption
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TestBatchProcessor executed - stopping consumption');

        $this->commandTester->execute([
            'processor' => 'test.batch.processor',
            '--initQueue' => true,
        ]);
    }

    public function testCommandFailsWithNonExistentProcessor(): void
    {
        $this->expectException(\Exception::class);

        $this->commandTester->execute([
            'processor' => 'non.existent.processor',
        ]);
    }

    public function testCommandRequiresProcessorArgument(): void
    {
        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "processor")');

        $this->commandTester->execute([]);
    }
}
