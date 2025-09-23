<?php

declare(strict_types=1);

namespace MessageBusBundle\Service;

use MessageBusBundle\MessageBus;
use MessageBusBundle\Producer\ProducerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ProducerService
{
    public const CLASS_HEADER = 'php.class_name';

    protected ProducerInterface $producer;
    protected SerializerInterface $serializer;

    public function __construct(ProducerInterface $producer, SerializerInterface $serializer)
    {
        $this->producer = $producer;
        $this->serializer = $serializer;
    }

    public function sendMessage(
        string $topic,
        string $message,
        array $headers = [],
        string $exchange = MessageBus::DEFAULT_EXCHANGE,
        int $delay = 0,
    ): self {
        $this->producer->send($topic, $message, $headers, $delay, $exchange);

        return $this;
    }

    public function sendDto(
        string $topic,
        object $dto,
        array $headers = [],
        string $exchange = MessageBus::DEFAULT_EXCHANGE,
        int $delay = 0,
    ): self {
        $headers[self::CLASS_HEADER] = get_class($dto);

        $message = $dto instanceof \JsonSerializable
            ? json_encode($dto)
            : $this->serializer->serialize($dto, 'json');

        return $this->sendMessage($topic, $message, $headers, $exchange, $delay);
    }
}
