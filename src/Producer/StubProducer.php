<?php

declare(strict_types=1);

namespace MessageBusBundle\Producer;

use Interop\Queue\Message;

class StubProducer implements ProducerInterface, EncoderProducerInterface
{
    private array $topics = [];

    private array $queues = [];

    public function send(string $topic, string $message, array $headers = [], int $delay = 0, ?string $exchange = null): self
    {
        $this->topics[$topic][] = ['message' => $message, 'headers' => $headers];

        return $this;
    }

    public function sendToQueue(string $queue, string $message, array $headers = [], int $delay = 0): self
    {
        $this->queues[$queue][] = ['message' => $message, 'headers' => $headers];

        return $this;
    }

    public function sendMessage(string $topic, Message $message, int $delay = 0, ?string $exchange = null): ProducerInterface
    {
        $this->topics[$topic][] = ['message' => $message, 'headers' => []];

        return $this;
    }

    public function sendMessageToQueue(string $queue, Message $message, int $delay = 0): ProducerInterface
    {
        $this->queues[$queue][] = ['message' => $message, 'headers' => []];

        return $this;
    }

    public function getAll(): array
    {
        return $this->topics;
    }

    public function getMessages(string $topic): array
    {
        return $this->topics[$topic] ?? [];
    }

    public function getSingleMessage(string $topic, int $key = 0): array
    {
        return json_decode($this->topics[$topic][$key]['message'], true);
    }

    public function countMessages(string $topic): int
    {
        return count($this->getMessages($topic));
    }

    public function clearTopics(): self
    {
        $this->topics = [];

        return $this;
    }

    public function getMessagesFromQueue(string $queue): array
    {
        return $this->queues[$queue] ?? [];
    }

    public function countMessagesInQueue(string $queue): int
    {
        return count($this->getMessagesFromQueue($queue));
    }
}
