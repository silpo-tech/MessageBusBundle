<?php

declare(strict_types=1);

namespace MessageBusBundle\EnqueueProcessor\ExceptionHandler;

use Interop\Queue\Context;
use Interop\Queue\Message;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;

class ChainExceptionHandler
{
    private \SplPriorityQueue $queue;

    public function __construct()
    {
        $this->queue = new \SplPriorityQueue();
    }

    public function addHandler(ExceptionHandlerInterface $handler, int $priority): self
    {
        $this->queue->insert($handler, $priority);

        return $this;
    }

    public function handle(ProcessorInterface $processor, \Throwable $exception, Message $message, Context $context): ?string
    {
        /** @var ExceptionHandlerInterface $handler */
        foreach (clone $this->queue as $handler) {
            if ($handler->supports($exception)) {
                $result = $handler->handle($exception, $message, $context, $processor);
                if (isset($result)) {
                    return $result;
                }
            }
        }

        return null;
    }
}
