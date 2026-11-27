<?php

declare(strict_types=1);

namespace MessageBusBundle\Messenger\Message;

/**
 * A message that carries a raw MessageBusBundle (enqueue) payload through Symfony Messenger.
 *
 * Both directions implement it so {@see \MessageBusBundle\Messenger\Serializer\EnqueueWireSerializer}
 * can read the same body + AMQP properties regardless of whether the message is inbound
 * (decoded from a queue) or outbound (about to be published).
 */
interface EnqueueWireMessageInterface
{
    public function getBody(): string;

    /**
     * @return array<string, mixed> AMQP application headers (incl. `php.class_name`)
     */
    public function getProperties(): array;
}
