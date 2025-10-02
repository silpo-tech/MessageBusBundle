<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\Stub\Processor;

use MessageBusBundle\EnqueueProcessor\Batch\AbstractBatchProcessor;
use MessageBusBundle\EnqueueProcessor\OptionsProcessorInterface;

abstract class TestOptionsProcessor extends AbstractBatchProcessor implements OptionsProcessorInterface
{
    abstract public function setOptions(array $options): void;
}
