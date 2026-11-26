<?php

declare(strict_types=1);

namespace MessageBusBundle\EventSubscriber;

use MessageBusBundle\Events;
use MessageBusBundle\Events\ConsumeEvent;
use MessageBusBundle\Events\PreConsumeEvent;
use MessageBusBundle\Service\ConsumerHeartbeat;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ConsumerHeartbeatSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ConsumerHeartbeat $heartbeat)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::CONSUME__PRE_START => 'onStart',
            Events::CONSUME__FINISHED => 'onFinish',
            Events::CONSUME__EXCEPTION => 'onFinish',
        ];
    }

    public function onStart(PreConsumeEvent $event): void
    {
        $this->heartbeat->attach($event->getContext());
    }

    public function onFinish(ConsumeEvent $event): void
    {
        $this->heartbeat->detach();
    }
}
