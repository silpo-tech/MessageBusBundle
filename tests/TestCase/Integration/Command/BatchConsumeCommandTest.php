<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Integration\Command;

use Interop\Amqp\Impl\AmqpMessage;
use MessageBusBundle\Command\BatchConsumeCommand;
use MessageBusBundle\EnqueueProcessor\Batch\Result;
use MessageBusBundle\Exception\NonBatchProcessorException;
use MessageBusBundle\Producer\ProducerInterface;
use MessageBusBundle\Tests\Kernel;
use MessageBusBundle\Tests\Stub\Processor\TestBatchOptionsProcessor;
use MessageBusBundle\Tests\Stub\Processor\TestBatchProcessor;
use PhpSolution\FunctionalTest\TestCase\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BatchConsumeCommandTest extends ApiTestCase
{
    private ContainerInterface $container;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $kernel = new Kernel('test', true);
        $kernel->boot();

        $this->container = $kernel->getContainer();

        /** @var BatchConsumeCommand $command */
        $command = $this->container->get(BatchConsumeCommand::class);

        $application = new Application();
        $application->add($command);

        $this->commandTester = new CommandTester($command);
    }

    #[DataProvider('executeDataProvider')]
    public function testCommandExecutesWithTestProcessor(Result $processorResult): void
    {
        /** @var TestBatchProcessor $processor */
        $processor = $this->container->get(TestBatchProcessor::class);
        $processor->result = $processorResult;

        /** @var ProducerInterface $producer */
        $producer = self::getContainer()->get(ProducerInterface::class);
        $producer->sendMessageToQueue(TestBatchProcessor::QUEUE, new AmqpMessage('Test message'));

        $result = $this->commandTester->execute([
            'processor' => 'test.batch.processor',
            '--initQueue' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertTrue($processor->executedSuccessfully, 'TestBatchProcessor should have executed successfully.');
    }

    public static function executeDataProvider(): iterable
    {
        yield 'reject' => [
            'processorResult' => Result::reject(1),
        ];

        yield 'acknowledged' => [
            'processorResult' => Result::ack(1),
        ];

        yield 'requeue' => [
            'processorResult' => Result::requeue(1, new \Exception()),
        ];
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
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "processor")');

        $this->commandTester->execute([]);
    }

    public function testCommandWithBatchProcessorOptions(): void
    {
        /** @var ProducerInterface $producer */
        $producer = self::getContainer()->get(ProducerInterface::class);
        $producer->sendMessageToQueue(TestBatchOptionsProcessor::QUEUE, new AmqpMessage('Test message'));

        // Expect the exception from TestBatchOptionsProcessor to early exit after options are set
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TestBatchOptionsProcessor setOptions called with:');

        // Test that processor options are set (this tests the setOptions path)
        $this->commandTester->execute([
            'processor' => 'test.batch.options.processor',
            '--initQueue' => true,
        ]);
    }

    public function testCommandFailsWithNonBatchProcessor(): void
    {
        $this->expectException(NonBatchProcessorException::class);
        $this->expectExceptionMessage('test.non.abstract.batch.processor processor is not support batch consume');

        // Use a processor that implements BatchProcessorInterface but not AbstractBatchProcessor
        $this->commandTester->execute([
            'processor' => 'test.non.abstract.batch.processor',
        ]);
    }
}
