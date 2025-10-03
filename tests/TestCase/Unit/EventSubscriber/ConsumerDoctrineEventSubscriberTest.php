<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EventSubscriber;

use Doctrine\Persistence\ObjectManager;
use MessageBusBundle\Events;
use MessageBusBundle\EventSubscriber\ConsumerDoctrineEventSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

// Custom interface for testing that includes isOpen method
interface TestableObjectManager extends ObjectManager
{
    public function isOpen(): bool;
}

class ConsumerDoctrineEventSubscriberTest extends TestCase
{
    private ManagerRegistry $managerRegistry;
    private ConsumerDoctrineEventSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->managerRegistry = $this->createMock(ManagerRegistry::class);
        $this->subscriber = new ConsumerDoctrineEventSubscriber($this->managerRegistry);
    }

    public function testImplementsEventSubscriberInterface(): void
    {
        $this->assertInstanceOf(EventSubscriberInterface::class, $this->subscriber);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = ConsumerDoctrineEventSubscriber::getSubscribedEvents();

        $expectedEvents = [
            Events::CONSUME__FINISHED => 'clearDoctrine',
            Events::CONSUME__EXCEPTION => 'clearDoctrine',
            Events::BATCH_CONSUME__FINISHED => 'clearDoctrine',
            Events::BATCH_CONSUME__EXCEPTION => 'clearDoctrine',
        ];

        $this->assertEquals($expectedEvents, $events);
    }

    public function testClearDoctrineWithOpenManagers(): void
    {
        $manager1 = $this->createMock(TestableObjectManager::class);
        $manager2 = $this->createMock(TestableObjectManager::class);

        $manager1->expects($this->once())->method('isOpen')->willReturn(true);
        $manager2->expects($this->once())->method('isOpen')->willReturn(true);
        $manager1->expects($this->once())->method('clear');
        $manager2->expects($this->once())->method('clear');

        $this->managerRegistry->expects($this->once())
            ->method('getManagers')
            ->willReturn(['default' => $manager1, 'secondary' => $manager2]);

        $this->managerRegistry->expects($this->never())->method('resetManager');

        $this->subscriber->clearDoctrine();
    }

    public function testClearDoctrineWithClosedManagers(): void
    {
        $manager1 = $this->createMock(TestableObjectManager::class);
        $manager2 = $this->createMock(TestableObjectManager::class);
        $resetManager = $this->createMock(TestableObjectManager::class);

        $manager1->expects($this->once())->method('isOpen')->willReturn(false);
        $manager2->expects($this->once())->method('isOpen')->willReturn(false);
        $manager1->expects($this->never())->method('clear');
        $manager2->expects($this->never())->method('clear');

        $this->managerRegistry->expects($this->once())
            ->method('getManagers')
            ->willReturn(['default' => $manager1, 'secondary' => $manager2]);

        $this->managerRegistry->expects($this->exactly(2))
            ->method('resetManager')
            ->willReturnCallback(function ($name) use ($resetManager) {
                static $callCount = 0;
                ++$callCount;
                if (1 === $callCount) {
                    $this->assertEquals('default', $name);
                } else {
                    $this->assertEquals('secondary', $name);
                }

                return $resetManager;
            });

        $this->subscriber->clearDoctrine();
    }

    public function testClearDoctrineWithMixedManagers(): void
    {
        $openManager = $this->createMock(TestableObjectManager::class);
        $closedManager = $this->createMock(TestableObjectManager::class);

        $openManager->expects($this->once())->method('isOpen')->willReturn(true);
        $closedManager->expects($this->once())->method('isOpen')->willReturn(false);
        $openManager->expects($this->once())->method('clear');
        $closedManager->expects($this->never())->method('clear');

        $this->managerRegistry->expects($this->once())
            ->method('getManagers')
            ->willReturn(['open' => $openManager, 'closed' => $closedManager]);

        $this->managerRegistry->expects($this->once())
            ->method('resetManager')
            ->with('closed');

        $this->subscriber->clearDoctrine();
    }

    public function testClearDoctrineWithNoManagers(): void
    {
        $this->managerRegistry->expects($this->once())
            ->method('getManagers')
            ->willReturn([]);

        $this->managerRegistry->expects($this->never())->method('resetManager');

        $this->subscriber->clearDoctrine();
    }

    public function testClearDoctrineWithSingleManager(): void
    {
        $manager = $this->createMock(TestableObjectManager::class);

        $manager->expects($this->once())->method('isOpen')->willReturn(true);
        $manager->expects($this->once())->method('clear');

        $this->managerRegistry->expects($this->once())
            ->method('getManagers')
            ->willReturn(['default' => $manager]);

        $this->subscriber->clearDoctrine();
    }
}
