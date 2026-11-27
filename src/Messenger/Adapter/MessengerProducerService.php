<?php

declare(strict_types=1);

namespace MessageBusBundle\Messenger\Adapter;

use MessageBusBundle\MessageBus;
use MessageBusBundle\Messenger\Message\PublishEnqueueMessage;
use MessageBusBundle\Producer\ProducerInterface;
use MessageBusBundle\Service\ProducerService;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Drop-in replacement for {@see ProducerService} that publishes through Symfony Messenger when
 * enabled, and falls back to the original enqueue behaviour when not — selected by a single
 * configuration flag, with no changes to producer code and no message attributes.
 *
 * All producers call `sendDto()`/`sendMessage()`; since `sendDto()` funnels into `sendMessage()`,
 * overriding just `sendMessage()` re-routes every publish path. The topic passed by the caller
 * becomes the AMQP routing key, so no per-message routing metadata is required. The emitted wire
 * format is identical to the legacy producer's, keeping legacy consumers interoperable.
 */
final class MessengerProducerService extends ProducerService
{
    public function __construct(
        ProducerInterface $producer,
        SerializerInterface $serializer,
        private readonly MessageBusInterface $messageBus,
        private readonly bool $useMessenger,
    ) {
        parent::__construct($producer, $serializer);
    }

    public function sendMessage(
        string $topic,
        string $message,
        array $headers = [],
        string $exchange = MessageBus::DEFAULT_EXCHANGE,
        int $delay = 0,
    ): ProducerService {
        if (!$this->useMessenger) {
            return parent::sendMessage($topic, $message, $headers, $exchange, $delay);
        }

        $stamps = [new AmqpStamp($topic)];
        if ($delay > 0) {
            $stamps[] = new DelayStamp($delay);
        }

        $this->messageBus->dispatch(
            new PublishEnqueueMessage($topic, $message, $headers, $exchange, $delay),
            $stamps,
        );

        return $this;
    }
}
