<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EventSubscriber;

use Doctrine\Persistence\ObjectManager;
use MessageBusBundle\EventSubscriber\ConsumerDoctrineEventSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\ManagerRegistry;

// Custom interface for testing that includes isOpen method
interface TestableObjectManager extends ObjectManager
{
    public function isOpen(): bool;
}

class ConsumerDoctrineEventSubscriberTest extends TestCase
{
    #[DataProvider('clearDoctrineProvider')]
    public function testClearDoctrine(array $managersConfig, array $expectedClearCalls, array $expectedResetCalls): void
    {
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managers = [];

        foreach ($managersConfig as $name => $isOpen) {
            $manager = $this->createMock(TestableObjectManager::class);
            $manager->method('isOpen')->willReturn($isOpen);

            if (in_array($name, $expectedClearCalls, true)) {
                $manager->expects($this->once())->method('clear');
            } else {
                $manager->expects($this->never())->method('clear');
            }

            $managers[$name] = $manager;
        }

        $managerRegistry->method('getManagers')->willReturn($managers);

        if (!empty($expectedResetCalls)) {
            $managerRegistry->expects($this->exactly(count($expectedResetCalls)))
                ->method('resetManager')
                ->with($this->callback(function ($name) use ($expectedResetCalls) {
                    return in_array($name, $expectedResetCalls, true);
                }));
        } else {
            $managerRegistry->expects($this->never())->method('resetManager');
        }

        $subscriber = new ConsumerDoctrineEventSubscriber($managerRegistry);
        $subscriber->clearDoctrine();
    }

    public static function clearDoctrineProvider(): array
    {
        return [
            'all managers open' => [
                'managersConfig' => ['default' => true, 'secondary' => true],
                'expectedClearCalls' => ['default', 'secondary'],
                'expectedResetCalls' => [],
            ],
            'mixed open/closed managers' => [
                'managersConfig' => ['default' => false, 'secondary' => true],
                'expectedClearCalls' => ['secondary'],
                'expectedResetCalls' => ['default'],
            ],
            'all managers closed' => [
                'managersConfig' => ['default' => false, 'secondary' => false],
                'expectedClearCalls' => [],
                'expectedResetCalls' => ['default', 'secondary'],
            ],
            'single open manager' => [
                'managersConfig' => ['default' => true],
                'expectedClearCalls' => ['default'],
                'expectedResetCalls' => [],
            ],
        ];
    }
}
