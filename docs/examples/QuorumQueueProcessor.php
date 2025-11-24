<?php

declare(strict_types=1);

namespace App\MessageBus\Processor;

use Interop\Queue\Context;
use Interop\Queue\Message;
use MessageBusBundle\AmqpTools\QueueType;
use MessageBusBundle\EnqueueProcessor\AbstractProcessor;

/**
 * Example processor that uses a quorum queue for high availability.
 * 
 * Quorum queues provide:
 * - Data replication across multiple RabbitMQ nodes
 * - Automatic leader election
 * - Better data safety guarantees
 */
class CriticalPaymentProcessor extends AbstractProcessor
{
    public function getSubscribedRoutingKeys(): array
    {
        return [
            'payment.critical.queue' => [
                'payment.created',
                'payment.confirmed',
                'payment.refunded',
            ],
        ];
    }

    /**
     * Use quorum queue for critical payment processing
     * to ensure no data loss even if RabbitMQ nodes fail.
     */
    public function getQueueType(): QueueType
    {
        return QueueType::QUORUM;
    }

    public function doProcess($body, Message $message, Context $session): string
    {
        // Process critical payment message
        $this->logger->info('Processing critical payment', ['body' => $body]);

        // Your business logic here
        // ...

        return self::ACK;
    }
}
