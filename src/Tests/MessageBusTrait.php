<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests;

use Interop\Amqp\AmqpContext;
use Interop\Amqp\Impl\AmqpMessage;
use MessageBusBundle\Producer\ProducerInterface;
use MessageBusBundle\Producer\StubProducer;
use MessageBusBundle\Service\ProducerService;
use Symfony\Component\Serializer\SerializerInterface;

trait MessageBusTrait
{
    public static function assertSingleMessage(array $message, string $topic)
    {
        self::assertEquals(1, self::getMessageBusProducer()->countMessages($topic));
        self::assertEquals($message, self::getMessageBusProducer()->getSingleMessage($topic));
    }

    public static function getMessageBusProducer(): StubProducer
    {
        return self::getContainer()->get(ProducerInterface::class);
    }

    public function sendTopic(string $processorClass, $body, array $properties = [], array $headers = []): string
    {
        return self::getContainer()
            ->get($processorClass)
            ->doProcess($body, new AmqpMessage(json_encode($body), $properties, $headers), $this->prophesize(AmqpContext::class)->reveal());
    }

    public function sendTopicNative(string $processorClass, $body, array $properties = [], array $headers = []): string
    {
        if (is_object($body)) {
            $properties[ProducerService::CLASS_HEADER] = get_class($body);

            $body = $body instanceof \JsonSerializable
                ? json_encode($body)
                : self::getContainer()->get(SerializerInterface::class)->serialize($body, 'json');
        } elseif (is_array($body)) {
            $body = json_encode($body);
        } else {
            $body = (string) $body;
        }

        return self::getContainer()
            ->get($processorClass)
            ->process(new AmqpMessage($body, $properties, $headers), $this->prophesize(AmqpContext::class)->reveal());
    }
}
