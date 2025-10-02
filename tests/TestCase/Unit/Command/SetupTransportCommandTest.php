<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Command;

use MessageBusBundle\AmqpTools\RabbitMqQueueManager;
use MessageBusBundle\Command\SetupTransportCommand;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SetupTransportCommandTest extends TestCase
{
    private RabbitMqQueueManager|MockObject $queueManager;
    private SetupTransportCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->queueManager = $this->createMock(RabbitMqQueueManager::class);
    }

    #[DataProvider('processorConfigurationDataProvider')]
    public function testInitializesQueuesBasedOnProcessorConfiguration(
        array $processorData,
        array $expectedInitQueueCalls,
        int $expectedExitCode = Command::SUCCESS
    ): void {
        // Convert processor data to actual mock objects
        $processors = [];
        foreach ($processorData as $data) {
            if (is_array($data)) {
                $processors[] = $this->createProcessorMock($data);
            } else {
                $processors[] = $data; // Non-processor objects like stdClass
            }
        }

        $this->command = new SetupTransportCommand($this->queueManager, $processors);

        $application = new Application();
        $application->add($this->command);
        $this->commandTester = new CommandTester($this->command);

        if (empty($expectedInitQueueCalls)) {
            $this->queueManager->expects($this->never())->method('initQueue');
        } else {
            $this->queueManager->expects($this->exactly(count($expectedInitQueueCalls)))
                ->method('initQueue')
                ->willReturnCallback(function ($queue, $routingKeys) use ($expectedInitQueueCalls) {
                    $this->assertArrayHasKey($queue, $expectedInitQueueCalls);
                    $this->assertEquals($expectedInitQueueCalls[$queue], $routingKeys);
                });
        }

        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals($expectedExitCode, $exitCode);
    }

    public static function processorConfigurationDataProvider(): iterable
    {
        yield 'single processor with multiple queues' => [
            'processorData' => [
                [
                    'user.queue' => ['user.created', 'user.updated'],
                    'notification.queue' => ['notification.send'],
                ],
            ],
            'expectedInitQueueCalls' => [
                'user.queue' => ['user.created', 'user.updated'],
                'notification.queue' => ['notification.send'],
            ],
        ];

        yield 'multiple processors with different queues' => [
            'processorData' => [
                [
                    'order.queue' => ['order.created', 'order.updated'],
                ],
                [
                    'payment.queue' => ['payment.processed'],
                ],
            ],
            'expectedInitQueueCalls' => [
                'order.queue' => ['order.created', 'order.updated'],
                'payment.queue' => ['payment.processed'],
            ],
        ];

        yield 'processor with empty routing keys' => [
            'processorData' => [
                [],
            ],
            'expectedInitQueueCalls' => [],
        ];

        yield 'empty processors list' => [
            'processorData' => [],
            'expectedInitQueueCalls' => [],
        ];

        yield 'mixed valid processors and non-processor objects' => [
            'processorData' => [
                [
                    'valid.queue' => ['valid.routing.key'],
                ],
                new \stdClass(), // Non-processor object should be skipped
                'invalid_string', // Invalid type should be skipped
            ],
            'expectedInitQueueCalls' => [
                'valid.queue' => ['valid.routing.key'],
            ],
        ];

        yield 'processor with complex routing configuration' => [
            'processorData' => [
                [
                    'events.queue' => ['event.user.*', 'event.order.*', 'event.payment.*'],
                    'logs.queue' => ['log.error', 'log.warning'],
                    'metrics.queue' => ['metric.performance'],
                ],
            ],
            'expectedInitQueueCalls' => [
                'events.queue' => ['event.user.*', 'event.order.*', 'event.payment.*'],
                'logs.queue' => ['log.error', 'log.warning'],
                'metrics.queue' => ['metric.performance'],
            ],
        ];
    }

    public function testCommandName(): void
    {
        $this->command = new SetupTransportCommand($this->queueManager, []);
        $this->assertEquals('messagebus:setup', $this->command->getName());
    }

    public function testCommandDescription(): void
    {
        $this->command = new SetupTransportCommand($this->queueManager, []);
        $description = $this->command->getDescription();

        if (!empty($description)) {
            $this->assertStringContainsString('queue', strtolower($description));
        } else {
            $this->expectNotToPerformAssertions();
        }
    }

    public function testHandlesQueueManagerExceptions(): void
    {
        $processor = $this->createProcessorMock([
            'failing.queue' => ['test.routing.key'],
        ]);

        $this->command = new SetupTransportCommand($this->queueManager, [$processor]);

        $application = new Application();
        $application->add($this->command);
        $this->commandTester = new CommandTester($this->command);

        $this->queueManager->expects($this->once())
            ->method('initQueue')
            ->with('failing.queue', ['test.routing.key'])
            ->willThrowException(new \Exception('Queue initialization failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Queue initialization failed');

        $this->commandTester->execute([]);
    }

    private function createProcessorMock(array $routingKeys): ProcessorInterface
    {
        $processor = $this->createMock(ProcessorInterface::class);
        $processor->method('getSubscribedRoutingKeys')->willReturn($routingKeys);

        return $processor;
    }
}
