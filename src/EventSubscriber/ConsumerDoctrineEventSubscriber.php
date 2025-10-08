<?php

declare(strict_types=1);

namespace MessageBusBundle\EventSubscriber;

use MessageBusBundle\Events;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConsumerDoctrineEventSubscriber implements EventSubscriberInterface
{
    private ManagerRegistry $managerRegistry;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    /**
     * @codeCoverageIgnore
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Events::CONSUME__FINISHED => 'clearDoctrine',
            Events::CONSUME__EXCEPTION => 'clearDoctrine',
            Events::BATCH_CONSUME__FINISHED => 'clearDoctrine',
            Events::BATCH_CONSUME__EXCEPTION => 'clearDoctrine',
        ];
    }

    public function clearDoctrine(): void
    {
        foreach ($this->managerRegistry->getManagers() as $name => $manager) {
            $manager->isOpen()
                ? $manager->clear()
                : $this->managerRegistry->resetManager($name);
        }
    }
}
