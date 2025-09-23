<?php

namespace MessageBusBundle\Command;

use MessageBusBundle\AmqpTools\RabbitMqQueueManager;
use MessageBusBundle\Consumption\BatchQueueConsumer;
use MessageBusBundle\Enqueue\BatchProcessorRegistryInterface;
use MessageBusBundle\EnqueueProcessor\Batch\AbstractBatchProcessor;
use MessageBusBundle\EnqueueProcessor\OptionsProcessorInterface;
use MessageBusBundle\Exception\NonBatchProcessorException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BatchConsumeCommand extends Command
{
    protected static $defaultName = 'messagebus:batch:consume';

    private BatchQueueConsumer $queueConsumer;
    private BatchProcessorRegistryInterface $processorRegistry;
    private RabbitMqQueueManager $rmqQueueManager;

    public function __construct(
        BatchQueueConsumer $queueConsumer,
        BatchProcessorRegistryInterface $processorRegistry,
        RabbitMqQueueManager $rmqQueueManager
    ) {
        $this->queueConsumer = $queueConsumer;
        $this->processorRegistry = $processorRegistry;
        $this->rmqQueueManager = $rmqQueueManager;

        parent::__construct(static::$defaultName);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('A worker that consumes message from a broker. '.
                'To use this broker you have to explicitly set a queue to consume from '.
                'and a message processor service')
            ->addArgument('processor', InputArgument::REQUIRED, 'A message processor.')
            ->addOption('initQueue', null, InputOption::VALUE_NONE, 'Queue has to be initialized')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $processor = $this->processorRegistry->get($input->getArgument('processor'));

        if ($processor instanceof OptionsProcessorInterface) {
            $processor->setOptions($input->getOptions());
        }

        if (!($processor instanceof AbstractBatchProcessor)) {
            throw new NonBatchProcessorException(sprintf('%s processor is not support batch consume', $input->getArgument('processor')));
        }

        if ($input->getOption('initQueue')) {
            foreach ($processor->getSubscribedRoutingKeys() as $queueName => $routingKeys) {
                $this->rmqQueueManager->initQueue($queueName, $routingKeys);
            }
        }

        $queue = array_key_first($processor->getSubscribedRoutingKeys());
        $this->queueConsumer->bind($queue, $processor);
        $this->queueConsumer->consume();

        return 0;
    }
}
