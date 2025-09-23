<?php

declare(strict_types=1);

namespace MessageBusBundle\Producer;

use Interop\Queue\Message;
use MessageBusBundle\Encoder\EncoderInterface;
use MessageBusBundle\MessageBus;

class EncoderProducer implements ProducerInterface, EncoderProducerInterface
{
    private ProducerInterface $producer;
    private EncoderInterface $encoder;

    /**
     * @todo Set Encoder via config
     */
    public function __construct(ProducerInterface $producer, EncoderInterface $encoder)
    {
        $this->producer = $producer;
        $this->encoder = $encoder;
    }

    public function send(
        string $topic,
        string $message,
        array $headers = [],
        int $delay = 0,
        string $exchange = MessageBus::DEFAULT_EXCHANGE
    ): ProducerInterface {
        if (!isset($headers[MessageBus::ENCODING_HEADER])) {
            $message = $this->encode($message);
            $headers[MessageBus::ENCODING_HEADER] = $this->encoder->getEncoding();
        }

        $this->producer->send($topic, $message, $headers, $delay, $exchange);

        return $this;
    }

    public function sendToQueue(string $queue, string $message, array $headers = [], int $delay = 0): ProducerInterface
    {
        if (!isset($headers[MessageBus::ENCODING_HEADER])) {
            $message = $this->encode($message);
            $headers[MessageBus::ENCODING_HEADER] = $this->encoder->getEncoding();
        }

        $this->producer->sendToQueue($queue, $message, $headers, $delay);

        return $this;
    }

    public function sendMessage(
        string $topic,
        Message $message,
        int $delay = 0,
        string $exchange = MessageBus::DEFAULT_EXCHANGE
    ): ProducerInterface {
        if (!$message->getHeader(MessageBus::ENCODING_HEADER)) {
            $message->setBody($this->encode($message->getBody()));
            $message->setHeader(MessageBus::ENCODING_HEADER, $this->encoder->getEncoding());
        }

        $this->producer->sendMessage($topic, $message, $delay, $exchange);

        return $this;
    }

    public function sendMessageToQueue(string $queue, Message $message, int $delay = 0): ProducerInterface
    {
        if (!$message->getHeader(MessageBus::ENCODING_HEADER)) {
            $message->setBody($this->encode($message->getBody()));
            $message->setHeader(MessageBus::ENCODING_HEADER, $this->encoder->getEncoding());
        }

        $this->producer->sendMessageToQueue($queue, $message, $delay);

        return $this;
    }

    private function encode(string $message): string
    {
        $data = $this->encoder->encode($message);
        if (null === $data) {
            throw new \InvalidArgumentException('Invalid message. Cannot encode this string');
        }

        return $data;
    }
}
