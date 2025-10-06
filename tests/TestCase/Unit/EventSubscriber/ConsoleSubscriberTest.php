<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EventSubscriber;

use MessageBusBundle\Command\BatchConsumeCommand;
use MessageBusBundle\Command\ConsumeCommand;
use MessageBusBundle\EventSubscriber\ConsoleSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleSubscriberTest extends TestCase
{
    #[DataProvider('commandProvider')]
    public function testOnConsoleCommand(string $commandClass, int $expectedAddOptionCalls): void
    {
        /** @var Command&MockObject $command */
        $command = $this->createMock($commandClass);

        if ($expectedAddOptionCalls > 0) {
            $command->expects($this->exactly($expectedAddOptionCalls))
                ->method('addOption')
                ->with($this->callback(function ($name) {
                    return in_array($name, ['option1', 'option2']);
                }), null, InputOption::VALUE_OPTIONAL);
        } else {
            $command->expects($this->never())
                ->method('addOption');
        }

        $event = new ConsoleCommandEvent(
            $command,
            $this->createMock(InputInterface::class),
            $this->createMock(OutputInterface::class)
        );

        $subscriber = new ConsoleSubscriber(['option1', 'option2']);
        $subscriber->onConsoleCommand($event);
    }

    public static function commandProvider(): array
    {
        return [
            'ConsumeCommand' => [
                'commandClass' => ConsumeCommand::class,
                'expectedAddOptionCalls' => 2,
            ],
            'BatchConsumeCommand' => [
                'commandClass' => BatchConsumeCommand::class,
                'expectedAddOptionCalls' => 2,
            ],
            'Other Command' => [
                'commandClass' => Command::class,
                'expectedAddOptionCalls' => 0,
            ],
        ];
    }
}
