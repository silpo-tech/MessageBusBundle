<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Producer;

use Interop\Queue\Message;
use MessageBusBundle\Producer\StubProducer;
use MessageBusBundle\Tests\DataProvider\ProducerDataProvider;
use PhpSolution\FunctionalTest\TestCase\Traits\FixturesTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StubProducerTest extends TestCase
{
    use FixturesTrait;

    private StubProducer $producer;

    protected function setUp(): void
    {
        $this->producer = new StubProducer();
    }

    #[DataProvider('providerSendData')]
    public function testSend(array $data): void
    {
        $result = $this->producer->send(
            $data['topic'],
            $data['message'],
            $data['headers']
        );

        $this->assertSame($this->producer, $result);
        $this->assertEquals(1, $this->producer->countMessages($data['topic']));

        $messages = $this->producer->getMessages($data['topic']);
        $this->assertEquals($data['message'], $messages[0]['message']);
        $this->assertEquals($data['headers'], $messages[0]['headers']);
    }

    #[DataProvider('providerQueueData')]
    public function testSendToQueue(array $data): void
    {
        $result = $this->producer->sendToQueue(
            $data['queue'],
            $data['message'],
            $data['headers']
        );

        $this->assertSame($this->producer, $result);
        $this->assertEquals(1, $this->producer->countMessagesInQueue($data['queue']));
    }

    public function testSendMessage(): void
    {
        $message = $this->createMock(Message::class);

        $result = $this->producer->sendMessage('test-topic', $message);

        $this->assertSame($this->producer, $result);
        $this->assertEquals(1, $this->producer->countMessages('test-topic'));
    }

    public function testGetSingleMessage(): void
    {
        $fixture = self::getFixturesFromJson('Producer/producer-message.json');

        $this->producer->send('test-topic', json_encode($fixture));

        $result = $this->producer->getSingleMessage('test-topic');

        $this->assertEquals($fixture, $result);
    }

    public static function providerSendData(): iterable
    {
        return ProducerDataProvider::sendData();
    }

    public static function providerQueueData(): iterable
    {
        return ProducerDataProvider::queueData();
    }
}
