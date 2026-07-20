<?php

declare(strict_types=1);

namespace MessageBusBundle\Messenger\Bridge;

use Interop\Queue\Context;
use Interop\Queue\Processor;
use MapperBundle\Mapper\MapperInterface;
use MessageBusBundle\EnqueueProcessor\AbstractProcessor;
use MessageBusBundle\Events;
use MessageBusBundle\Events\ConsumeEvent;
use MessageBusBundle\Events\ExceptionConsumeEvent;
use MessageBusBundle\Events\PreConsumeEvent;
use MessageBusBundle\Exception\RejectException;
use MessageBusBundle\Exception\RequeueException;
use MessageBusBundle\Service\ProducerService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Bridges Symfony Messenger to the existing MessageBusBundle processors under `messenger:consume`.
 *
 * It mirrors {@see AbstractProcessor::process()} step for step — same lifecycle events, same
 * `php.class_name` body mapping — but calls {@see AbstractProcessor::doProcess()} directly and lets
 * outcomes bubble to Messenger instead of the bundle's ChainExceptionHandler. So retry/failure are
 * owned by Messenger (`retry_strategy` + `failure_transport`), while the processor classes and the
 * subscribers on the `consume.*` events (Doctrine clear, Sentry, timing, gzip decode) stay untouched.
 *
 * Outcome translation:
 *   - REQUEUE (status) / RequeueException  → rethrown as an ordinary exception so `retry_strategy`
 *     governs it (max_retries + exponential delay). NOT RecoverableMessageHandlingException, which
 *     would force a retry past max_retries and loop forever. The attempt count round-trips through
 *     the `requeue-count` header (see {@see \MessageBusBundle\Messenger\Serializer\EnqueueWireSerializer}),
 *     so a self-capping processor still stops itself exactly as under enqueue.
 *   - REJECT (status) / RejectException    → UnrecoverableMessageHandlingException → failure_transport.
 *   - ACK / null                           → acknowledged.
 */
#[AsMessageHandler]
final class EnqueueProcessorBridgeHandler
{
    public function __construct(
        private readonly ProcessorRegistry $processors,
        private readonly EnqueueMessageFactory $messageFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MapperInterface $mapper,
        private readonly Context $context,
    ) {
    }

    public function __invoke(EnqueueEnvelope $envelope): void
    {
        $processor = $this->processors->get($envelope->getProcessorId());

        if (!$processor instanceof AbstractProcessor) {
            throw new \LogicException(sprintf('Processor "%s" must extend %s to run through the native Messenger bridge.', $envelope->getProcessorId(), AbstractProcessor::class));
        }

        $message = $this->messageFactory->create($envelope);
        $processorClass = $processor::class;

        // consume.pre_start: runs before decoding, exactly as in process(). Most importantly this is
        // where EncodedMessageEventSubscriber gunzips the raw body in place (Sentry span + timing too).
        $this->eventDispatcher->dispatch(new PreConsumeEvent($message, $this->context, $processorClass), Events::CONSUME__PRE_START);

        $body = json_decode($message->getBody(), true);
        if ($destinationClass = $message->getProperty(ProducerService::CLASS_HEADER)) {
            $body = class_exists($destinationClass) ? $this->mapper->convert($body, $destinationClass) : $body;
        }

        $consumeEvent = new ConsumeEvent($body, $message, $this->context, $processorClass);
        $this->eventDispatcher->dispatch($consumeEvent, Events::CONSUME__START);

        try {
            $status = $processor->doProcess($body, $message, $this->context);
        } catch (\Throwable $exception) {
            $this->eventDispatcher->dispatch(
                new ExceptionConsumeEvent($exception, $body, $message, $this->context, $processorClass),
                Events::CONSUME__EXCEPTION,
            );

            throw $exception instanceof RejectException ? new UnrecoverableMessageHandlingException($exception->getMessage(), 0, $exception) : $exception;
        }

        // consume.finished fires for every non-throwing outcome (incl. REQUEUE/REJECT status), matching
        // process(); this is what triggers the Doctrine clear before we translate the status below.
        $this->eventDispatcher->dispatch($consumeEvent, Events::CONSUME__FINISHED);

        match ($status) {
            Processor::REJECT => throw new UnrecoverableMessageHandlingException('Processor rejected the message.'),
            Processor::REQUEUE => throw new RequeueException('Processor requested a requeue.'),
            default => null,
        };
    }
}
