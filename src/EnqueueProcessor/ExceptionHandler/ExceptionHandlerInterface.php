<?php

declare(strict_types=1);

namespace MessageBusBundle\EnqueueProcessor\ExceptionHandler;

use Interop\Queue\Context;
use Interop\Queue\Message;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;
use Throwable;

interface ExceptionHandlerInterface
{
    public function handle(
        Throwable $exception,
        Message $message,
        Context $context,
        ProcessorInterface $processor,
    ): string|null;

    public function supports(Throwable $exception): bool;
}
