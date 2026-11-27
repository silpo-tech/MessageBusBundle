<?php

declare(strict_types=1);

namespace MessageBusBundle\Service;

use Enqueue\AmqpLib\AmqpContext;
use Interop\Queue\Context;
use PhpAmqpLib\Connection\AbstractConnection;

/**
 * Sends AMQP heartbeats from long-running code executed inside a message handler.
 *
 * php-amqplib is single-threaded: heartbeats are sent only when the connection is touched.
 * Call beat() from loops and between slow steps so the connection survives long processing.
 *
 * Safe to call anywhere: does nothing when no consumer is attached (HTTP requests, CLI, tests).
 */
final class ConsumerHeartbeat
{
    private const MIN_INTERVAL_SECONDS = 5.0;

    private ?AbstractConnection $connection = null;

    private float $lastBeat = 0.0;

    public function attach(Context $context): void
    {
        $this->connection = $context instanceof AmqpContext
            ? $context->getLibChannel()->getConnection()
            : null;
        $this->lastBeat = 0.0;
    }

    public function detach(): void
    {
        $this->connection = null;
    }

    /**
     * Cheap to call often: the connection is checked at most once per MIN_INTERVAL_SECONDS.
     */
    public function beat(): void
    {
        if (null === $this->connection) {
            return;
        }

        $now = microtime(true);
        if ($now - $this->lastBeat < self::MIN_INTERVAL_SECONDS) {
            return;
        }

        $this->lastBeat = $now;
        $this->connection->checkHeartBeat();
    }

    /**
     * Calls beat() on every iteration. Use it in foreach over a long collection.
     *
     * @template TKey
     * @template TValue
     *
     * @param iterable<TKey, TValue> $items
     *
     * @return \Generator<TKey, TValue>
     */
    public function wrap(iterable $items): \Generator
    {
        foreach ($items as $key => $item) {
            $this->beat();

            yield $key => $item;
        }
    }
}
