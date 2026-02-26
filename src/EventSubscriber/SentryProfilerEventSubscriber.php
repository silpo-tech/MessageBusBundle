<?php

declare(strict_types=1);

namespace MessageBusBundle\EventSubscriber;

use MessageBusBundle\Events;
use MessageBusBundle\Events\BatchConsumeEvent;
use MessageBusBundle\Events\PreConsumeEvent;
use Sentry\SentrySdk;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @codeCoverageIgnore
 */
class SentryProfilerEventSubscriber implements EventSubscriberInterface
{
    private ?Transaction $transaction = null;

    public static function getSubscribedEvents(): array
    {
        return [
            Events::BATCH_CONSUME__START => ['startTransaction', 1000],
            Events::BATCH_CONSUME__FINISHED => ['successTransaction', -1000],
            Events::BATCH_CONSUME__EXCEPTION => ['errorTransaction', -1000],
            Events::CONSUME__PRE_START => ['startAnonymousTransaction', 1000],
            Events::CONSUME__FINISHED => ['successTransaction', -1000],
            Events::CONSUME__EXCEPTION => ['errorTransaction', -1000],
        ];
    }

    public function startTransaction(BatchConsumeEvent $batchConsumeEvent): void
    {
        if ($this->sentryTransactionSupports() && null === $this->transaction) {
            SentrySdk::getCurrentHub()->pushScope();

            $context = new TransactionContext();
            $context->setName('MBus '.$batchConsumeEvent->getProcessorClass());
            $context->setOp('processor.batchProcess');
            $context->setTags([
                'sf.messages' => (string) count($batchConsumeEvent->getMessagesBatch()),
            ]);

            $this->transaction = SentrySdk::getCurrentHub()->startTransaction($context);

            SentrySdk::getCurrentHub()->setSpan($this->transaction);
        }
    }

    public function startAnonymousTransaction(PreConsumeEvent $event): void
    {
        if ($this->sentryTransactionSupports() && null === $this->transaction) {
            SentrySdk::getCurrentHub()->pushScope();

            $context = new TransactionContext();
            $context->setName('MBus '.$event->getProcessorClass());
            $context->setOp('processor.process');

            $this->transaction = SentrySdk::getCurrentHub()->startTransaction($context);

            SentrySdk::getCurrentHub()->setSpan($this->transaction);
        }
    }

    public function successTransaction(): void
    {
        if ($this->sentryTransactionSupports() && null !== $this->transaction) {
            $this->transaction->setStatus(SpanStatus::ok());
            $this->finishTransaction();
        }
    }

    public function errorTransaction(): void
    {
        if ($this->sentryTransactionSupports() && null !== $this->transaction) {
            $this->transaction->setStatus(SpanStatus::unknownError());
            $this->finishTransaction();
        }
    }

    private function sentryTransactionSupports(): bool
    {
        return class_exists(TransactionContext::class);
    }

    private function finishTransaction(): void
    {
        $this->transaction->finish();
        $this->transaction = null;
        SentrySdk::getCurrentHub()->popScope();
    }
}
