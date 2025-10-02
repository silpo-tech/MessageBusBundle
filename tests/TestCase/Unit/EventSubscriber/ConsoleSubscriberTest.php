<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EventSubscriber;

use MessageBusBundle\Command\BatchConsumeCommand;
use MessageBusBundle\Command\ConsumeCommand;
use MessageBusBundle\EventSubscriber\ConsoleSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleSubscriberTest extends TestCase
{
    private ConsoleSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new ConsoleSubscriber(['option1', 'option2']);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = ConsoleSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(ConsoleEvents::COMMAND, $events);
        $this->assertEquals('onConsoleCommand', $events[ConsoleEvents::COMMAND]);
    }

    public function testOnConsoleCommandWithConsumeCommand(): void
    {
        $command = $this->createMock(ConsumeCommand::class);
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $event = new ConsoleCommandEvent($command, $input, $output);

        $command->expects($this->exactly(2))
            ->method('addOption')
            ->with($this->callback(function ($name) {
                return in_array($name, ['option1', 'option2']);
            }), null, InputOption::VALUE_OPTIONAL);

        $this->subscriber->onConsoleCommand($event);
    }

    public function testOnConsoleCommandWithBatchConsumeCommand(): void
    {
        $command = $this->createMock(BatchConsumeCommand::class);
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $event = new ConsoleCommandEvent($command, $input, $output);

        $command->expects($this->exactly(2))
            ->method('addOption')
            ->with($this->callback(function ($name) {
                return in_array($name, ['option1', 'option2']);
            }), null, InputOption::VALUE_OPTIONAL);

        $this->subscriber->onConsoleCommand($event);
    }

    public function testOnConsoleCommandWithOtherCommand(): void
    {
        $command = $this->createMock(Command::class);
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $event = new ConsoleCommandEvent($command, $input, $output);

        $command->expects($this->never())
            ->method('addOption');

        $this->subscriber->onConsoleCommand($event);
    }
}
