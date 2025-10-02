<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Integration\Command;

use Interop\Queue\Context;
use MessageBusBundle\Command\RequeueFailedCommand;
use MessageBusBundle\Producer\ProducerInterface;
use MessageBusBundle\Tests\DataProvider\FailedMessageGenerator;
use MessageBusBundle\Tests\Kernel;
use MessageBusBundle\Tests\MessageBusTrait;
use PhpAmqpLib\Message\AMQPMessage;
use PhpSolution\FunctionalTest\TestCase\Traits\FixturesTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use SilpoTech\Lib\TestUtilities\TestCase\Traits\UtilityTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class RequeueFailedCommandTest extends KernelTestCase
{
    use MessageBusTrait;
    use FixturesTrait;
    use UtilityTrait;

    private const SOURCE_QUEUE_NAME = 'queue.failed';
    private const DESTINATION_QUEUE_NAME = 'destination-queue';
    private const ROUTING_KEY = 'route-name';

    private static array $fixtures = [];
    private static array $correlationIds = [];

    public static function setUpBeforeClass(): void
    {
        self::$kernel = new Kernel('test', true);
        self::$kernel->boot();
    }

    public function setUp(): void
    {
        $this->prepareQueues();

        self::$fixtures = self::publishToFailedQueue(100);
        self::$correlationIds = [];
    }

    #[DataProvider('successDataProvider')]
    public function testSuccess(array $data = [], array $conditions = [], array $expected = []): void
    {
        [$data, $conditions, $expected] = self::resolveValues($data, $conditions, $expected);

        $data = is_callable($data['input']) ? $data['input']() : $data['input'];

        $application = new Application(self::$kernel);
        $command = $application->find(RequeueFailedCommand::$defaultName);
        $commandTester = new CommandTester($command);
        $commandTester->execute($data);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString(sprintf('Messages processed: %d', $expected['messageProcessed']), $output);

        $expected['assert']();
    }

    public static function successDataProvider(): iterable
    {
        yield 'all messages from source queue successfully moved, source queue is empty' => [
            'data' => [
                'input' => [
                    'source-queue' => self::SOURCE_QUEUE_NAME,
                    'dest-queue' => self::DESTINATION_QUEUE_NAME,
                    'topic' => self::ROUTING_KEY,
                ],
            ],
            'conditions' => [],
            'expected' => [
                'result' => Command::SUCCESS,
                'messageProcessed' => 100,
                'assert' => static fn (): callable => static function (): void {
                    /** @var AMQPMessage[] $processedMessages */
                    $processedMessages = self::getMessagesFromQueue(self::DESTINATION_QUEUE_NAME);

                    self::assertCount(100, $processedMessages);
                    self::assertCount(0, self::getMessagesFromQueue(self::SOURCE_QUEUE_NAME));

                    $routingKey = $processedMessages[0]->get_properties()['application_headers']['enqueue.topic'];
                    self::assertEquals('route-name', $routingKey);

                    $phpClass = $processedMessages[0]->get_properties()['application_headers']['php.class_name'];
                    self::assertEquals('SDK\Product\DTO\ObjectDTO', $phpClass);
                },
            ],
        ];

        yield 'all messages from source queue successfully copied, source queue is not changed' => [
            'data' => [
                'input' => [
                    'source-queue' => self::SOURCE_QUEUE_NAME,
                    'dest-queue' => self::DESTINATION_QUEUE_NAME,
                    'topic' => self::ROUTING_KEY,
                    '--no-ack' => true,
                ],
            ],
            'conditions' => [],
            'expected' => [
                'result' => Command::SUCCESS,
                'messageProcessed' => 100,
                'assert' => static fn (): callable => static function (): void {
                    self::assertCount(100, self::getMessagesFromQueue(self::DESTINATION_QUEUE_NAME));
                    self::assertCount(100, self::getMessagesFromQueue(self::SOURCE_QUEUE_NAME));
                },
            ],
        ];

        yield 'only limited messages qty are moved' => [
            'data' => [
                'input' => [
                    'source-queue' => self::SOURCE_QUEUE_NAME,
                    'dest-queue' => self::DESTINATION_QUEUE_NAME,
                    'topic' => self::ROUTING_KEY,
                    '--message-limit' => 33,
                ],
            ],
            'conditions' => [],
            'expected' => [
                'result' => Command::SUCCESS,
                'messageProcessed' => 33,
                'assert' => static fn (): callable => static function (): void {
                    self::assertCount(33, self::getMessagesFromQueue(self::DESTINATION_QUEUE_NAME));
                    self::assertCount(67, self::getMessagesFromQueue(self::SOURCE_QUEUE_NAME));
                },
            ],
        ];

        yield 'only messages with specified correlationId are moved' => [
            'data' => [
                'input' => static fn (): callable => static function (): array {
                    self::$correlationIds = array_rand(self::$fixtures, 7);

                    return
                        [
                            'source-queue' => self::SOURCE_QUEUE_NAME,
                            'dest-queue' => self::DESTINATION_QUEUE_NAME,
                            'topic' => self::ROUTING_KEY,
                            '--correlationIds' => self::$correlationIds,
                        ];
                },
            ],
            'conditions' => [],
            'expected' => [
                'result' => Command::SUCCESS,
                'messageProcessed' => 7,
                'assert' => static fn (): callable => static function (): void {
                    $processedMessages = self::getMessagesFromQueue(self::DESTINATION_QUEUE_NAME);

                    self::assertCount(7, $processedMessages);
                    self::assertCount(93, self::getMessagesFromQueue(self::SOURCE_QUEUE_NAME));

                    $processedCorrelationIds = [];
                    foreach ($processedMessages as $message) {
                        $processedCorrelationIds[] = $message->get_properties()['correlation_id'] ?? null;
                    }

                    ksort(self::$correlationIds);
                    ksort($processedCorrelationIds);

                    self::assertEquals($processedCorrelationIds, self::$correlationIds);
                },
            ],
        ];
    }

    #[DataProvider('failedDataProvider')]
    public function testFailed(array $data = [], array $conditions = [], array $expected = []): void
    {
        [$data, $conditions, $expected] = self::resolveValues($data, $conditions, $expected);

        $application = new Application(self::$kernel);
        $command = $application->find(RequeueFailedCommand::$defaultName);
        $commandTester = new CommandTester($command);
        $result = $commandTester->execute($data);

        self::assertEquals(Command::INVALID, $result);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString($expected['message'], $output);
    }

    public static function failedDataProvider(): iterable
    {
        yield 'missing input parameters' => [
            'data' => [],
            'conditions' => [],
            'expected' => [
                'result' => Command::INVALID,
                'message' => 'Source queue is not specified',
            ],
        ];

        yield 'missing input parameter source-queue' => [
            'data' => [
                'dest-queue' => self::DESTINATION_QUEUE_NAME,
            ],
            'conditions' => [],
            'expected' => [
                'result' => Command::INVALID,
                'message' => 'Source queue is not specified',
            ],
        ];

        yield 'missing input parameter destination-queue' => [
            'data' => [
                'source-queue' => self::SOURCE_QUEUE_NAME,
            ],
            'conditions' => [],
            'expected' => [
                'result' => Command::INVALID,
                'message' => 'Destination queue is not specified',
            ],
        ];

        yield 'missing input parameter routing-key' => [
            'data' => [
                'source-queue' => self::SOURCE_QUEUE_NAME,
                'dest-queue' => self::DESTINATION_QUEUE_NAME,
            ],
            'conditions' => [],
            'expected' => [
                'result' => Command::INVALID,
                'message' => 'Routing key is not specified',
            ],
        ];

        yield 'wrong source-queue' => [
            'data' => [
                'source-queue' => 'some.wrong.queue',
                'dest-queue' => self::DESTINATION_QUEUE_NAME,
                'topic' => self::ROUTING_KEY,
            ],
            'conditions' => [],
            'expected' => [
                'result' => Command::INVALID,
                'message' => 'It looks like the source queue is not a queue of failed messages',
            ],
        ];

        yield 'wrong input parameter message-limit' => [
            'data' => [
                'source-queue' => self::SOURCE_QUEUE_NAME,
                'dest-queue' => self::DESTINATION_QUEUE_NAME,
                'topic' => self::ROUTING_KEY,
                '--message-limit' => 'wrong',
            ],
            'conditions' => [],
            'expected' => [
                'result' => Command::INVALID,
                'message' => 'The messages limit option (-l) must be a number',
            ],
        ];

        yield 'specified source queue is not found on the broker' => [
            'data' => [
                'source-queue' => 'not-existing.queue.failed',
                'dest-queue' => self::DESTINATION_QUEUE_NAME,
                'topic' => self::ROUTING_KEY,
            ],
            'conditions' => [],
            'expected' => [
                'result' => Command::INVALID,
                'message' => 'NOT_FOUND - no queue',
            ],
        ];

        yield 'specified destination queue is not found on the broker' => [
            'data' => [
                'source-queue' => self::SOURCE_QUEUE_NAME,
                'dest-queue' => 'not-existing.queue',
                'topic' => self::ROUTING_KEY,
            ],
            'conditions' => [],
            'expected' => [
                'result' => Command::INVALID,
                'message' => 'NOT_FOUND - no queue',
            ],
        ];
    }

    private function prepareQueues(): void
    {
        $context = self::getContainer()->get(Context::class);
        $channel = $context->getLibChannel();
        $channel->queue_delete(self::SOURCE_QUEUE_NAME);
        $channel->queue_delete(self::DESTINATION_QUEUE_NAME);
        $channel->queue_declare(queue: self::SOURCE_QUEUE_NAME, durable: true, auto_delete: false);
        $channel->queue_declare(queue: self::DESTINATION_QUEUE_NAME, durable: true, auto_delete: false);
    }

    private static function publishToFailedQueue(int $messagesQty): array
    {
        $producer = self::getContainer()->get(ProducerInterface::class);
        $context = self::getContainer()->get(Context::class);

        $messages = FailedMessageGenerator::generateMessages($messagesQty);
        foreach ($messages as $message) {
            $producer->sendMessageToQueue(
                self::SOURCE_QUEUE_NAME,
                $context->convertMessage($message),
            );
        }

        return $messages;
    }

    private static function getMessagesFromQueue(string $queue): array
    {
        $channel = self::getContainer()->get(Context::class)->getLibChannel();

        $messages = [];
        while (true) {
            $message = $channel->basic_get($queue);
            if (null === $message) {
                break;
            }

            $messages[] = $message;
        }

        return $messages;
    }
}
