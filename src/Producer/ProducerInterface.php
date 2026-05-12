<?php

declare(strict_types=1);

namespace MessageBusBundle\Producer;

use Interop\Queue\Message;
use Interop\Queue\Queue;
use MessageBusBundle\AmqpTools\QueueType;
use MessageBusBundle\MessageBus;

interface ProducerInterface
{
    public function send(string $topic, string $message, array $headers = [], int $delay = 0, string $exchange = MessageBus::DEFAULT_EXCHANGE): self;

    public function sendToQueue(string $queue, string $message, array $headers = [], int $delay = 0): self;

    public function sendMessage(string $topic, Message $message, int $delay = 0, string $exchange = MessageBus::DEFAULT_EXCHANGE): self;

    public function sendMessageToQueue(string $queue, Message $message, int $delay = 0, QueueType $queueType = QueueType::DEFAULT): self;

    public function sendMessageToExistingQueue(Queue $queue, Message $message, int $delay = 0): self;
}
