<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Producer;

use Interop\Queue\Message;
use MessageBusBundle\Producer\StubProducer;
use PHPUnit\Framework\TestCase;

class StubProducerTest extends TestCase
{
    private StubProducer $producer;

    protected function setUp(): void
    {
        $this->producer = new StubProducer();
    }

    public function testSend(): void
    {
        $result = $this->producer->send('test.topic', 'test message', ['header1' => 'value1']);

        $this->assertSame($this->producer, $result);
        $this->assertEquals(1, $this->producer->countMessages('test.topic'));

        $messages = $this->producer->getMessages('test.topic');
        $this->assertEquals('test message', $messages[0]['message']);
        $this->assertEquals(['header1' => 'value1'], $messages[0]['headers']);
    }

    public function testSendWithoutHeaders(): void
    {
        $result = $this->producer->send('test.topic', 'test message');

        $this->assertSame($this->producer, $result);
        $messages = $this->producer->getMessages('test.topic');
        $this->assertEquals([], $messages[0]['headers']);
    }

    public function testSendWithDelayAndExchange(): void
    {
        $result = $this->producer->send('test.topic', 'test message', [], 5000, 'custom.exchange');

        $this->assertSame($this->producer, $result);
        $this->assertEquals(1, $this->producer->countMessages('test.topic'));
    }

    public function testSendMultipleMessages(): void
    {
        $this->producer->send('test.topic', 'message1');
        $this->producer->send('test.topic', 'message2');
        $this->producer->send('test.topic', 'message3');

        $this->assertEquals(3, $this->producer->countMessages('test.topic'));

        $messages = $this->producer->getMessages('test.topic');
        $this->assertEquals('message1', $messages[0]['message']);
        $this->assertEquals('message2', $messages[1]['message']);
        $this->assertEquals('message3', $messages[2]['message']);
    }

    public function testSendToQueue(): void
    {
        $result = $this->producer->sendToQueue('test.queue', 'test message', ['header1' => 'value1']);

        $this->assertSame($this->producer, $result);
        $this->assertEquals(1, $this->producer->countMessagesInQueue('test.queue'));

        $messages = $this->producer->getMessagesFromQueue('test.queue');
        $this->assertEquals('test message', $messages[0]['message']);
        $this->assertEquals(['header1' => 'value1'], $messages[0]['headers']);
    }

    public function testSendToQueueWithoutHeaders(): void
    {
        $result = $this->producer->sendToQueue('test.queue', 'test message');

        $this->assertSame($this->producer, $result);
        $messages = $this->producer->getMessagesFromQueue('test.queue');
        $this->assertEquals([], $messages[0]['headers']);
    }

    public function testSendToQueueWithDelay(): void
    {
        $result = $this->producer->sendToQueue('test.queue', 'test message', [], 3000);

        $this->assertSame($this->producer, $result);
        $this->assertEquals(1, $this->producer->countMessagesInQueue('test.queue'));
    }

    public function testSendMessage(): void
    {
        $message = $this->createMock(Message::class);

        $result = $this->producer->sendMessage('test.topic', $message);

        $this->assertSame($this->producer, $result);
        $this->assertEquals(1, $this->producer->countMessages('test.topic'));

        $messages = $this->producer->getMessages('test.topic');
        $this->assertSame($message, $messages[0]['message']);
        $this->assertEquals([], $messages[0]['headers']);
    }

    public function testSendMessageWithDelayAndExchange(): void
    {
        $message = $this->createMock(Message::class);

        $result = $this->producer->sendMessage('test.topic', $message, 2000, 'custom.exchange');

        $this->assertSame($this->producer, $result);
        $this->assertEquals(1, $this->producer->countMessages('test.topic'));
    }

    public function testSendMessageToQueue(): void
    {
        $message = $this->createMock(Message::class);

        $result = $this->producer->sendMessageToQueue('test.queue', $message);

        $this->assertSame($this->producer, $result);
        $this->assertEquals(1, $this->producer->countMessagesInQueue('test.queue'));

        $messages = $this->producer->getMessagesFromQueue('test.queue');
        $this->assertSame($message, $messages[0]['message']);
        $this->assertEquals([], $messages[0]['headers']);
    }

    public function testSendMessageToQueueWithDelay(): void
    {
        $message = $this->createMock(Message::class);

        $result = $this->producer->sendMessageToQueue('test.queue', $message, 1000);

        $this->assertSame($this->producer, $result);
        $this->assertEquals(1, $this->producer->countMessagesInQueue('test.queue'));
    }

    public function testGetAll(): void
    {
        $this->producer->send('topic1', 'message1');
        $this->producer->send('topic2', 'message2');

        $all = $this->producer->getAll();

        $this->assertArrayHasKey('topic1', $all);
        $this->assertArrayHasKey('topic2', $all);
        $this->assertEquals('message1', $all['topic1'][0]['message']);
        $this->assertEquals('message2', $all['topic2'][0]['message']);
    }

    public function testGetAllEmpty(): void
    {
        $all = $this->producer->getAll();

        $this->assertEquals([], $all);
    }

    public function testGetMessages(): void
    {
        $this->producer->send('test.topic', 'message1', ['h1' => 'v1']);
        $this->producer->send('test.topic', 'message2', ['h2' => 'v2']);

        $messages = $this->producer->getMessages('test.topic');

        $this->assertCount(2, $messages);
        $this->assertEquals('message1', $messages[0]['message']);
        $this->assertEquals(['h1' => 'v1'], $messages[0]['headers']);
        $this->assertEquals('message2', $messages[1]['message']);
        $this->assertEquals(['h2' => 'v2'], $messages[1]['headers']);
    }

    public function testGetMessagesNonExistentTopic(): void
    {
        $messages = $this->producer->getMessages('non.existent');

        $this->assertEquals([], $messages);
    }

    public function testGetSingleMessage(): void
    {
        $testData = ['id' => 123, 'name' => 'test'];
        $this->producer->send('test.topic', json_encode($testData));

        $result = $this->producer->getSingleMessage('test.topic');

        $this->assertEquals($testData, $result);
    }

    public function testGetSingleMessageWithKey(): void
    {
        $testData1 = ['id' => 1, 'name' => 'first'];
        $testData2 = ['id' => 2, 'name' => 'second'];

        $this->producer->send('test.topic', json_encode($testData1));
        $this->producer->send('test.topic', json_encode($testData2));

        $result1 = $this->producer->getSingleMessage('test.topic', 0);
        $result2 = $this->producer->getSingleMessage('test.topic', 1);

        $this->assertEquals($testData1, $result1);
        $this->assertEquals($testData2, $result2);
    }

    public function testCountMessages(): void
    {
        $this->assertEquals(0, $this->producer->countMessages('test.topic'));

        $this->producer->send('test.topic', 'message1');
        $this->assertEquals(1, $this->producer->countMessages('test.topic'));

        $this->producer->send('test.topic', 'message2');
        $this->assertEquals(2, $this->producer->countMessages('test.topic'));
    }

    public function testCountMessagesNonExistentTopic(): void
    {
        $count = $this->producer->countMessages('non.existent');

        $this->assertEquals(0, $count);
    }

    public function testClearTopics(): void
    {
        $this->producer->send('topic1', 'message1');
        $this->producer->send('topic2', 'message2');

        $this->assertEquals(1, $this->producer->countMessages('topic1'));
        $this->assertEquals(1, $this->producer->countMessages('topic2'));

        $result = $this->producer->clearTopics();

        $this->assertSame($this->producer, $result);
        $this->assertEquals(0, $this->producer->countMessages('topic1'));
        $this->assertEquals(0, $this->producer->countMessages('topic2'));
        $this->assertEquals([], $this->producer->getAll());
    }

    public function testGetMessagesFromQueue(): void
    {
        $this->producer->sendToQueue('test.queue', 'message1', ['h1' => 'v1']);
        $this->producer->sendToQueue('test.queue', 'message2', ['h2' => 'v2']);

        $messages = $this->producer->getMessagesFromQueue('test.queue');

        $this->assertCount(2, $messages);
        $this->assertEquals('message1', $messages[0]['message']);
        $this->assertEquals(['h1' => 'v1'], $messages[0]['headers']);
        $this->assertEquals('message2', $messages[1]['message']);
        $this->assertEquals(['h2' => 'v2'], $messages[1]['headers']);
    }

    public function testGetMessagesFromQueueNonExistent(): void
    {
        $messages = $this->producer->getMessagesFromQueue('non.existent');

        $this->assertEquals([], $messages);
    }

    public function testCountMessagesInQueue(): void
    {
        $this->assertEquals(0, $this->producer->countMessagesInQueue('test.queue'));

        $this->producer->sendToQueue('test.queue', 'message1');
        $this->assertEquals(1, $this->producer->countMessagesInQueue('test.queue'));

        $this->producer->sendToQueue('test.queue', 'message2');
        $this->assertEquals(2, $this->producer->countMessagesInQueue('test.queue'));
    }

    public function testCountMessagesInQueueNonExistent(): void
    {
        $count = $this->producer->countMessagesInQueue('non.existent');

        $this->assertEquals(0, $count);
    }

    public function testMixedTopicsAndQueues(): void
    {
        $this->producer->send('test.topic', 'topic message');
        $this->producer->sendToQueue('test.queue', 'queue message');

        $this->assertEquals(1, $this->producer->countMessages('test.topic'));
        $this->assertEquals(1, $this->producer->countMessagesInQueue('test.queue'));

        $topicMessages = $this->producer->getMessages('test.topic');
        $queueMessages = $this->producer->getMessagesFromQueue('test.queue');

        $this->assertEquals('topic message', $topicMessages[0]['message']);
        $this->assertEquals('queue message', $queueMessages[0]['message']);
    }

    public function testClearTopicsDoesNotAffectQueues(): void
    {
        $this->producer->send('test.topic', 'topic message');
        $this->producer->sendToQueue('test.queue', 'queue message');

        $this->producer->clearTopics();

        $this->assertEquals(0, $this->producer->countMessages('test.topic'));
        $this->assertEquals(1, $this->producer->countMessagesInQueue('test.queue'));
    }

    public function testInterfaceImplementation(): void
    {
        $this->assertInstanceOf(\MessageBusBundle\Producer\ProducerInterface::class, $this->producer);
        $this->assertInstanceOf(\MessageBusBundle\Producer\EncoderProducerInterface::class, $this->producer);
    }
}
