<?php

declare(strict_types=1);

namespace MessageBusBundle\EnqueueProcessor;

interface OptionsProcessorInterface
{
    public function setOptions(array $options): void;

    public function getOption(string $name): mixed;
}
