<?php

declare(strict_types=1);

namespace MessageBusBundle\Exception;

class ValidationException extends MessageBusException
{
    private iterable $violations;

    public function __construct(
        string $message = '',
        iterable $violations = [],
        int $code = 0,
        \Throwable|null $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
        $this->violations = $violations;
    }

    public function getViolations(): iterable
    {
        return $this->violations;
    }
}
