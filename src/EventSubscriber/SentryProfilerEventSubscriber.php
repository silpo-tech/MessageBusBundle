<?php

declare(strict_types=1);

namespace MessageBusBundle\EventSubscriber;

use MessageBusBundle\Events;
use MessageBusBundle\Events\BatchConsumeEvent;
use MessageBusBundle\Events\PreConsumeEvent;
use Sentry\SentrySdk;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SentryProfilerEventSubscriber implements EventSubscriberInterface
{
    private ?Span $transaction = null;

    public function startTransaction(BatchConsumeEvent $batchConsumeEvent): void
    {
        if (null === $this->transaction && class_exists(TransactionContext::class)) {
            $sentryTransactionContext = new TransactionContext();
            $sentryTransactionContext->setName('MBus '.$batchConsumeEvent->getProcessorClass());
            $sentryTransactionContext->setOp('processor.batchProcess');
            $sentryTransactionContext->setTags([
                'sf.messages' => count($batchConsumeEvent->getMessagesBatch()),
            ]);

            $sentryTransaction = SentrySdk::getCurrentHub()->startTransaction($sentryTransactionContext);
            SentrySdk::getCurrentHub()->setSpan($sentryTransaction);

            $this->transaction = $sentryTransaction;
        }
    }

    public function startAnonymousTransaction(PreConsumeEvent $event): void
    {
        if (null === $this->transaction && class_exists(TransactionContext::class)) {
            $sentryTransactionContext = new TransactionContext();
            $sentryTransactionContext->setName('MBus '.$event->getProcessorClass());
            $sentryTransactionContext->setOp('processor.process');

            $sentryTransaction = SentrySdk::getCurrentHub()->startTransaction($sentryTransactionContext);
            SentrySdk::getCurrentHub()->setSpan($sentryTransaction);

            $this->transaction = $sentryTransaction;
        }
    }

    public function successTransaction(): void
    {
        if ($this->transaction instanceof Transaction && class_exists(TransactionContext::class)) {
            $this->transaction->setStatus(SpanStatus::ok());
        }

        $this->finishTransaction();
    }

    public function errorTransaction(): void
    {
        if (!class_exists(TransactionContext::class)) {
            return;
        }

        if ($this->transaction instanceof Transaction) {
            $this->transaction->setStatus(SpanStatus::unknownError());
        }

        $this->finishTransaction();
    }

    private function finishTransaction(): void
    {
        if ($this->transaction instanceof Transaction && class_exists(TransactionContext::class)) {
            $this->transaction->finish();
            $this->transaction = null;
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::BATCH_CONSUME__START => 'startTransaction',
            Events::BATCH_CONSUME__FINISHED => 'successTransaction',
            Events::BATCH_CONSUME__EXCEPTION => 'errorTransaction',
            Events::CONSUME__PRE_START => 'startAnonymousTransaction',
            Events::CONSUME__FINISHED => 'successTransaction',
            Events::CONSUME__EXCEPTION => 'errorTransaction',
        ];
    }
}
