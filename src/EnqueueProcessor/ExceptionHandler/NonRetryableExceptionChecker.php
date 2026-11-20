<?php

declare(strict_types=1);

namespace MessageBusBundle\EnqueueProcessor\ExceptionHandler;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class NonRetryableExceptionChecker
{
    /**
     * @param class-string<\Throwable>[] $nonRetryableExceptions
     */
    public function __construct(
        #[Autowire(param: 'messagebus.requeue.non_retryable_exceptions')]
        private readonly array $nonRetryableExceptions = [],
    ) {
    }

    /**
     * @param class-string<\Throwable>[] $additionalNonRetryableExceptions
     */
    public function isNonRetryable(\Throwable $e, array $additionalNonRetryableExceptions = []): bool
    {
        foreach ([...$this->nonRetryableExceptions, ...$additionalNonRetryableExceptions] as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
