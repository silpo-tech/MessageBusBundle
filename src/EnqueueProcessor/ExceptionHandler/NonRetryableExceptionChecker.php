<?php

declare(strict_types=1);

namespace MessageBusBundle\EnqueueProcessor\ExceptionHandler;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class NonRetryableExceptionChecker
{
    private const DEFAULT_NON_RETRYABLE = [
        \InvalidArgumentException::class,
        \UnexpectedValueException::class,
        \TypeError::class,
        \ValueError::class,
    ];

    /**
     * @param class-string<\Throwable>[] $nonRetryableExceptions
     */
    public function __construct(
        #[Autowire(param: 'messagebus.requeue.non_retryable_exceptions')]
        private readonly array $nonRetryableExceptions = [],
    ) {
    }

    public function isNonRetryable(\Throwable $e): bool
    {
        foreach ([...self::DEFAULT_NON_RETRYABLE, ...$this->nonRetryableExceptions] as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
