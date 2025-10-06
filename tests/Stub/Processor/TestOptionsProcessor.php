<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\Stub\Processor;

use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use MessageBusBundle\EnqueueProcessor\OptionsProcessorInterface;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;

class TestOptionsProcessor implements Processor, ProcessorInterface, OptionsProcessorInterface
{
    public const QUEUE = 'test.options.queue';

    private array $options = [];

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getOption(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function process(Message $message, Context $context): string
    {
        // Throw exception to prevent infinite consumption in tests
        throw new \RuntimeException('TestOptionsProcessor executed with options: '.json_encode($this->options));
    }

    public function getSubscribedRoutingKeys(): array
    {
        return [
            self::QUEUE => ['test.options.routing'],
        ];
    }
}
