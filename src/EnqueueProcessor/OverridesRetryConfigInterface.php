<?php

declare(strict_types=1);

namespace MessageBusBundle\EnqueueProcessor;

interface OverridesRetryConfigInterface
{
    public function getMaxRetries(): int;
}
