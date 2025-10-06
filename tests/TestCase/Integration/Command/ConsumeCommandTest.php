<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Integration\Command;

use Interop\Amqp\Impl\AmqpMessage;
use MessageBusBundle\Command\ConsumeCommand;
use MessageBusBundle\Producer\ProducerInterface;
use MessageBusBundle\Service\ProducerService;
use MessageBusBundle\Tests\Kernel;
use MessageBusBundle\Tests\Stub\Processor\Message\Envelope;
use MessageBusBundle\Tests\Stub\Processor\TestProcessor;
use PhpSolution\FunctionalTest\TestCase\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ConsumeCommandTest extends ApiTestCase
{
    private CommandTester $commandTester;
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $kernel = new Kernel('test', true);
        $kernel->boot();

        $this->container = $kernel->getContainer();

        /** @var ConsumeCommand $command */
        $command = $this->container->get(ConsumeCommand::class);

        $application = new Application();
        $application->add($command);

        $this->commandTester = new CommandTester($command);
    }

    #[DataProvider('executeDataProvider')]
    public function testCommandExecutesWithTestProcessor(
        string $message,
        ?callable $onProcessCallback = null,
        array $properties = []
    ): void {
        /** @var TestProcessor $processor */
        $processor = $this->container->get(TestProcessor::class);
        $processor->processCallback = $onProcessCallback;

        /** @var ProducerInterface $producer */
        $producer = self::getContainer()->get(ProducerInterface::class);
        $producer->sendMessageToQueue(TestProcessor::QUEUE, new AmqpMessage($message, $properties));

        // Expect the exception from TestProcessor to prevent infinite consumption
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TestProcessor executed - stopping consumption');

        $this->commandTester->execute([
            'processor' => 'test.processor',
            '--initQueue' => true,
        ]);
    }

    public static function executeDataProvider(): iterable
    {
        yield 'simple process' => [
            'message' => json_encode(['foo' => 'bar']),
            'onProcessCallback' => function (mixed $body) {
                self::assertSame(['foo' => 'bar'], $body);
            },
        ];

        yield 'process with class header' => [
            'message' => json_encode(['foo' => 'bar']),
            'onProcessCallback' => function (mixed $body) {
                self::assertInstanceOf(Envelope::class, $body);
            },
            'properties' => [
                ProducerService::CLASS_HEADER => Envelope::class,
            ],
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

    public function testCommandFailsWithUnsupportedTransport(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Transport "unsupported" is not supported.');

        $this->commandTester->execute([
            'processor' => 'test.processor',
            '--transport' => 'unsupported',
        ]);
    }

    public function testCommandWithProcessorOptions(): void
    {
        /** @var ProducerInterface $producer */
        $producer = self::getContainer()->get(ProducerInterface::class);
        $producer->sendMessageToQueue('test.options.queue', new AmqpMessage('Test message'));

        // Expect the exception from TestOptionsProcessor to prevent infinite consumption
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TestOptionsProcessor executed with options:');

        // Test that processor options are set (this tests the setOptions path)
        $this->commandTester->execute([
            'processor' => 'test.options.processor',
            '--initQueue' => true,
            '--transport' => 'default',
        ]);
    }

    public function testCommandWithLoggerExtension(): void
    {
        /** @var ProducerInterface $producer */
        $producer = self::getContainer()->get(ProducerInterface::class);
        $producer->sendMessageToQueue(TestProcessor::QUEUE, new AmqpMessage('Test message'));

        // Expect the exception from TestProcessor to prevent infinite consumption
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TestProcessor executed - stopping consumption');

        // Test with logger=stdout (this tests the logger extension array_unshift path)
        $this->commandTester->execute([
            'processor' => 'test.processor',
            '--initQueue' => true,
            '--logger' => 'stdout',
        ]);
    }
}
