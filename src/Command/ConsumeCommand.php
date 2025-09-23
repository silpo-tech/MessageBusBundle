<?php

namespace MessageBusBundle\Command;

use Enqueue\Consumption\ChainExtension;
use Enqueue\Consumption\QueueConsumerInterface;
use Enqueue\ProcessorRegistryInterface;
use Enqueue\Symfony\Consumption\ChooseLoggerCommandTrait;
use Enqueue\Symfony\Consumption\LimitsExtensionsCommandTrait;
use MessageBusBundle\AmqpTools\RabbitMqQueueManager;
use MessageBusBundle\EnqueueProcessor\OptionsProcessorInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConsumeCommand extends Command
{
    use LimitsExtensionsCommandTrait;
    use ChooseLoggerCommandTrait;

    protected static $defaultName = 'messagebus:consume';

    /**
     * @var ContainerInterface
     */
    private $container;

    private RabbitMqQueueManager $rmqQueueManager;

    /**
     * @var string
     */
    private $defaultTransport;

    /**
     * @var string
     */
    private $queueConsumerIdPattern;

    /**
     * @var string
     */
    private $processorRegistryIdPattern;

    public function __construct(
        ContainerInterface $container,
        RabbitMqQueueManager $rmqQueueManager,
        string $defaultTransport,
        string $queueConsumerIdPattern = 'enqueue.transport.%s.queue_consumer',
        string $processorRegistryIdPattern = 'enqueue.transport.%s.processor_registry'
    ) {
        $this->container = $container;
        $this->rmqQueueManager = $rmqQueueManager;
        $this->defaultTransport = $defaultTransport;
        $this->queueConsumerIdPattern = $queueConsumerIdPattern;
        $this->processorRegistryIdPattern = $processorRegistryIdPattern;

        parent::__construct(static::$defaultName);
    }

    protected function configure(): void
    {
        $this->configureLimitsExtensions();
        $this->configureLoggerExtension();

        $this
            ->setDescription('A worker that consumes message from a broker. '.
                'To use this broker you have to explicitly set a queue to consume from '.
                'and a message processor service')
            ->addArgument('processor', InputArgument::REQUIRED, 'A message processor.')
            ->addOption('transport', 't', InputOption::VALUE_OPTIONAL, 'The transport to consume messages from.', $this->defaultTransport)
            ->addOption('initQueue', null, InputOption::VALUE_NONE, 'Need to init queue.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $transport = $input->getOption('transport');

        try {
            $consumer = $this->getQueueConsumer($transport);
        } catch (NotFoundExceptionInterface $e) {
            throw new \LogicException(sprintf('Transport "%s" is not supported.', $transport), null, $e);
        }

        $processor = $this->getProcessorRegistry($transport)->get($input->getArgument('processor'));

        if ($processor instanceof OptionsProcessorInterface) {
            $processor->setOptions($input->getOptions());
        }

        if ($input->getOption('initQueue')) {
            foreach ($processor->getSubscribedRoutingKeys() as $queueName => $routingKeys) {
                $this->rmqQueueManager->initQueue($queueName, $routingKeys);
            }
        }

        $queue = array_key_first($processor->getSubscribedRoutingKeys());
        $consumer->bind($queue, $processor);

        $extensions = $this->getLimitsExtensions($input, $output);
        if ($loggerExtension = $this->getLoggerExtension($input, $output)) {
            array_unshift($extensions, $loggerExtension);
        }

        $consumer->consume(new ChainExtension($extensions));

        return 0;
    }

    private function getQueueConsumer(string $name): QueueConsumerInterface
    {
        return $this->container->get(sprintf($this->queueConsumerIdPattern, $name));
    }

    private function getProcessorRegistry(string $name): ProcessorRegistryInterface
    {
        return $this->container->get(sprintf($this->processorRegistryIdPattern, $name));
    }
}
