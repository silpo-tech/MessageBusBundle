<?php

namespace MessageBusBundle\Enqueue;

use LogicException;
use MessageBusBundle\EnqueueProcessor\Batch\BatchProcessorInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

final class ContainerBatchProcessorRegistry implements BatchProcessorRegistryInterface
{
    private ContainerInterface $locator;

    public function __construct(ContainerInterface $locator)
    {
        $this->locator = $locator;
    }

    public function get(string $processorName): BatchProcessorInterface
    {
        if (false == $this->locator->has($processorName)) {
            throw new LogicException(sprintf('Service locator does not have a processor with name "%s".', $processorName));
        }

        return $this->locator->get($processorName);
    }
}
