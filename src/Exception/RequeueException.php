<?php

declare(strict_types=1);

namespace MessageBusBundle\Exception;

use Throwable;

class RequeueException extends MessageBusException
{
    private int $count;

    public function __construct(string $reason, int $count = 5, Throwable|null $previous = null)
    {
        parent::__construct($reason, 0, $previous);
        $this->count = $count;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
