<?php

declare(strict_types=1);

namespace MessageBusBundle\Command;

use Interop\Amqp\AmqpContext;
use Interop\Queue\Context;
use MessageBusBundle\Producer\ProducerInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RequeueFailedCommand extends Command
{
    public static $defaultName = 'messagebus:requeue';

    private const MESSAGES_LIMIT = 200;
    private const ARGUMENT_SOURCE_QUEUE = 'source-queue';
    private const ARGUMENT_DESTINATION_QUEUE = 'dest-queue';
    private const ARGUMENT_ROUTING_KEY = 'topic';
    private const OPTION_CORRELATION_IDS = 'correlationIds';
    private const OPTION_ACK = 'ack';
    private const OPTION_MESSAGE_LIMIT = 'message-limit';

    private const REQUIRED_PROPERTIES = ['enqueue.topic', 'php.class_name'];

    public function __construct(
        private readonly Context $context,
        private readonly ProducerInterface $producer,
    ) {
        parent::__construct(self::$defaultName);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Move messages between queues.')
            ->addArgument(
                self::ARGUMENT_SOURCE_QUEUE,
                InputOption::VALUE_REQUIRED,
                'Source queue',
            )
            ->addArgument(
                self::ARGUMENT_DESTINATION_QUEUE,
                InputOption::VALUE_REQUIRED,
                'Destination queue',
            )
            ->addArgument(
                self::ARGUMENT_ROUTING_KEY,
                InputOption::VALUE_REQUIRED,
                'Routing key',
            )
            ->addOption(
                self::OPTION_CORRELATION_IDS,
                'c',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Correlation IDs',
                [],
            )
            ->addOption(
                self::OPTION_ACK,
                null,
                InputOption::VALUE_NEGATABLE,
                'Ack (or reject --no-ack) received messages',
                'ack',
            )
            ->addOption(
                self::OPTION_MESSAGE_LIMIT,
                'l',
                InputOption::VALUE_OPTIONAL,
                'Messages limit',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->context instanceof AmqpContext) {
            return Command::INVALID;
        }

        $io = new SymfonyStyle($input, $output);

        $channel = $this->context->getLibChannel();

        if (!$this->validateInput($input, $channel, $io)) {
            return Command::INVALID;
        }

        $totalMessagesCounter = 0;
        $processedMessagesCounter = 0;
        $messagesLimit = (int)($input->getOption(self::OPTION_MESSAGE_LIMIT) ?? self::MESSAGES_LIMIT);
        $correlationIds = $input->getOption(self::OPTION_CORRELATION_IDS);

        $io->progressStart($correlationIds ? sizeof($correlationIds) : $messagesLimit);

        $messagesToReject = [];

        do {
            $message = $channel->basic_get($input->getArgument(self::ARGUMENT_SOURCE_QUEUE));
            if ($message === null) {
                break;
            }

            $routingKey = $input->getArgument(self::ARGUMENT_ROUTING_KEY);
            if ($routingKey != $this->getHeaderValue($message, 'enqueue.topic')) {
                continue;
            }

            $totalMessagesCounter++;

            if ($correlationIds) {
                $messageCorrelationId = $this->getCorrelationId($message);

                if (!in_array($messageCorrelationId, $correlationIds)) {
                    $messagesToReject[] = $message;

                    continue;
                }
            }

            $this->publish($input->getArgument(self::ARGUMENT_DESTINATION_QUEUE), $message);
            $processedMessagesCounter ++;

            if ($input->getOption(self::OPTION_ACK)) {
                $message->ack();
            } else {
                $messagesToReject[] = $message;
            }

            $io->progressAdvance();
        } while ($totalMessagesCounter < $messagesLimit);

        $this->rejectMessages($messagesToReject);

        $io->newLine();

        $io->info(sprintf("Messages processed: %s", $processedMessagesCounter));

        return Command::SUCCESS;
    }

    private function validateInput(InputInterface $input, AMQPChannel $channel, SymfonyStyle $io): bool
    {
        $sourceQueue = $input->getArgument(self::ARGUMENT_SOURCE_QUEUE);
        if ($sourceQueue === null) {
            $io->error('Source queue is not specified');

            return false;
        }

        if (!str_ends_with($sourceQueue, '.failed')) {
            $io->error('It looks like the source queue is not a queue of failed messages');

            return false;
        }

        if ($input->getArgument(self::ARGUMENT_DESTINATION_QUEUE) === null) {
            $io->error('Destination queue is not specified');

            return false;
        }

        if ($input->getArgument(self::ARGUMENT_ROUTING_KEY) === null) {
            $io->error('Routing key is not specified');

            return false;
        }

        $limit = $input->getOption(self::OPTION_MESSAGE_LIMIT);
        if ($limit !== null & !is_numeric($limit)) {
            $io->error('The messages limit option (-l) must be a number');

            return false;
        }

        try {
            $channel->queue_declare($input->getArgument(self::ARGUMENT_SOURCE_QUEUE), true);
            $channel->queue_declare($input->getArgument(self::ARGUMENT_DESTINATION_QUEUE), true);
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return false;
        }

        return true;
    }
    private function getCorrelationId(AMQPMessage $message): string|null
    {
        $properties = $message->get_properties();

        return $properties['correlation_id'] ?? null;
    }

    private function getHeaderValue(AMQPMessage $message, string $key): string|null
    {
        $properties = $message->get_properties();

        $headers = $properties['application_headers'] ?? null;
        if (!$headers) {
            return null;
        }

        return $headers[$key] ?? null;
    }

    private function publish(string $queue, AMQPMessage $message): void
    {
        $messageToPublish = $this->context->convertMessage($message);

        $properties = [];
        foreach (self::REQUIRED_PROPERTIES as $propertyName) {
            if ($property = $messageToPublish->getProperty($propertyName)) {
                $properties[$propertyName] = $property;
            }
        }

        $messageToPublish->setProperties($properties);
        $this->producer->sendMessageToQueue($queue, $messageToPublish);
    }

    /**
     * @param AMQPMessage[] $messages
     */
    private function rejectMessages(array $messages): void
    {
        foreach ($messages as $message) {
            $message->reject();
        }
    }


}
