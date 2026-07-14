<?php

declare(strict_types=1);

namespace MessageBusBundle\Messenger\Bridge;

use Interop\Queue\Processor;
use Psr\Container\ContainerInterface;

/**
 * Locates existing MessageBusBundle processors (tagged `enqueue.transport.processor`)
 * by their service id, so the Messenger bridge can hand a decoded message to the same
 * processor that enqueue's `messagebus:consume` would have used.
 */
final class ProcessorRegistry
{
    /**
     * @param ContainerInterface $processors service locator over the `enqueue.transport.processor` tag,
     *                                        indexed by service id
     */
    public function __construct(private readonly ContainerInterface $processors)
    {
    }

    public function get(string $processorId): Processor
    {
        if (!$this->processors->has($processorId)) {
            throw new \InvalidArgumentException(
                sprintf('No enqueue processor registered with id "%s".', $processorId),
            );
        }

        $processor = $this->processors->get($processorId);

        if (!$processor instanceof Processor) {
            throw new \LogicException(
                sprintf('Service "%s" must implement %s to be used by the Messenger bridge.', $processorId, Processor::class),
            );
        }

        return $processor;
    }
}
