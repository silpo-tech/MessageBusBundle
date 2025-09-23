<?php

declare(strict_types=1);

namespace MessageBusBundle\EventSubscriber;

use MessageBusBundle\Command\BatchConsumeCommand;
use MessageBusBundle\Command\ConsumeCommand;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConsoleSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly array $allowOptions)
    {
    }

    public static function getSubscribedEvents()
    {
        return [ConsoleEvents::COMMAND => 'onConsoleCommand'];
    }

    public function onConsoleCommand(ConsoleCommandEvent $event)
    {
        if ($event->getCommand() instanceof ConsumeCommand || $event->getCommand() instanceof BatchConsumeCommand) {
            foreach ($this->allowOptions as $allowOption) {
                $event->getCommand()
                    ->addOption(name: $allowOption, mode: InputOption::VALUE_OPTIONAL);
            }
        }
    }
}
