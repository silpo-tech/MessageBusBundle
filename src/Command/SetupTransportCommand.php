<?php

declare(strict_types=1);

namespace MessageBusBundle\Command;

use Interop\Amqp\Impl\AmqpBind;
use Interop\Queue\Context;
use MessageBusBundle\AmqpTools\RabbitMqQueueManager;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;
use MessageBusBundle\MessageBus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetupTransportCommand extends Command
{
    protected static $defaultName = 'messagebus:setup';
    protected static $defaultDescription = 'Create queues and bind them to correct topics.';


    private RabbitMqQueueManager $rmqQueueManager;

    /**
     * @var iterable
     */
    private $processors;

    /**
     * @param RabbitMqQueueManager  $rmqQueueManager
     * @param iterable $handlers
     */
    public function __construct(RabbitMqQueueManager $rmqQueueManager, iterable $handlers)
    {
        $this->processors = $handlers;
        parent::__construct(self::$defaultName);
        $this->rmqQueueManager = $rmqQueueManager;
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->processors as $processor) {
            if ($processor instanceof ProcessorInterface) {
                $routings = $processor->getSubscribedRoutingKeys();
                foreach ($routings as $queueName => $routingKeys) {
                    $this->rmqQueueManager->initQueue($queueName, $routingKeys);
                }
            }
        }

        return 0;
    }
}
