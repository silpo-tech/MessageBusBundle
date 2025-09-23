<?php

declare(strict_types=1);

namespace MessageBusBundle\EnqueueProcessor;

trait OptionsProcessorAwareTrait
{
    protected array $optionsProcessor = [];

    public function setOptions(array $options): void
    {
        $this->optionsProcessor = $options;
    }

    public function getOption(string $name): mixed
    {
        return $this->optionsProcessor[$name] ?? null;
    }
}
