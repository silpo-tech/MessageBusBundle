<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\Stub\Processor;

use Interop\Queue\Context;
use MessageBusBundle\EnqueueProcessor\Batch\AbstractBatchProcessor;
use MessageBusBundle\EnqueueProcessor\ExceptionHandler\ChainExceptionHandler;
use MessageBusBundle\EnqueueProcessor\OptionsProcessorInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class TestBatchOptionsProcessor extends AbstractBatchProcessor implements OptionsProcessorInterface
{
    public const QUEUE = 'test.batch.options.queue';

    public function __construct()
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->chainExceptionHandler = new ChainExceptionHandler();
    }

    public function setOptions(array $options): void
    {
        // Throw exception immediately when setOptions is called to prove it was executed
        throw new \RuntimeException('TestBatchOptionsProcessor setOptions called with: '.json_encode($options));
    }

    public function getOption(string $name): mixed
    {
        return null;
    }

    public function doProcess(array $messages, Context $context): array
    {
        return [];
    }

    public function getSubscribedRoutingKeys(): array
    {
        return [
            self::QUEUE => ['test.batch.options.routing'],
        ];
    }
}
