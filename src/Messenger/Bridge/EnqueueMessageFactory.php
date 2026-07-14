<?php

declare(strict_types=1);

namespace MessageBusBundle\Messenger\Bridge;

use Interop\Amqp\Impl\AmqpMessage;

/**
 * Rebuilds an enqueue {@see \Interop\Queue\Message} from the carrier envelope so the
 * existing processor sees exactly the message it would have received under enqueue's
 * own consume loop — including properties like `php.class_name` and `requeue-count`.
 *
 * Mirrors the construction used by the bundle's own test trait
 * ({@see \MessageBusBundle\Tests\MessageBusTrait::sendTopicNative()}).
 */
final class EnqueueMessageFactory
{
    public function create(EnqueueEnvelope $envelope): AmqpMessage
    {
        return new AmqpMessage(
            $envelope->getBody(),
            $envelope->getProperties(),
            $envelope->getHeaders(),
        );
    }
}
