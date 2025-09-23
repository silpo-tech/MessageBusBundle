<?php

declare(strict_types=1);

namespace MessageBusBundle\EnqueueProcessor\ExceptionHandler;

trait ExceptionTraceGeneratorTrait
{
    protected function getExceptionTraceTrait(\Throwable $throwable, int $depth = 5): array
    {
        return array_slice($throwable->getTrace(), 0, $depth);
    }
}
