<?php

declare(strict_types=1);

namespace MessageBusBundle\EnqueueProcessor\ExceptionHandler;

use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use MessageBusBundle\EnqueueProcessor\ProcessorInterface;
use MessageBusBundle\Events\ConsumeRejectEvent;
use MessageBusBundle\Exception\RejectException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

class RejectHandler implements ExceptionHandlerInterface
{
    use ExceptionTraceGeneratorTrait;

    private const MESSAGE_REJECT = '[MessageBusBundle] Reject message';

    public function __construct(private readonly LoggerInterface $logger, private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    /**
     * @param RejectException $exception
     */
    public function handle(
        \Throwable $exception,
        Message $message,
        Context $context,
        ProcessorInterface $processor,
    ): string {
        $this->logger->debug(self::MESSAGE_REJECT, [
            'reason' => $exception->getMessage(),
            'trace' => $this->getExceptionTraceTrait($exception),
            'message' => $message->getBody(),
            'correlationId' => (string) $message->getCorrelationId(),
        ]);

        $this->eventDispatcher->dispatch(new ConsumeRejectEvent($exception, $message, $context, $processor::class));

        return Processor::REJECT;
    }

    public function supports(\Throwable $exception): bool
    {
        return $exception instanceof RejectException;
    }
}
