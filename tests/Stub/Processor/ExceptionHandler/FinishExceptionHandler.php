<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\Stub\Processor\ExceptionHandler;

use Interop\Queue\Context;
use Interop\Queue\Message;
use MessageBusBundle\EnqueueProcessor\ExceptionHandler\ExceptionHandlerInterface;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;

class FinishExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(
        \Throwable $exception,
        Message $message,
        Context $context,
        ProcessorInterface $processor,
    ): ?string {
        throw $exception;
    }

    public function supports(\Throwable $exception): bool
    {
        return true;
    }
}
