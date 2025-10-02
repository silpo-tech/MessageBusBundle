<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\DependencyInjection\CompilerPass;

use MessageBusBundle\DependencyInjection\CompilerPass\DoctrineSubscriberPass;
use MessageBusBundle\EventSubscriber\ConsumerDoctrineEventSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class DoctrineSubscriberPassTest extends TestCase
{
    private DoctrineSubscriberPass $pass;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->pass = new DoctrineSubscriberPass();
        $this->container = new ContainerBuilder();
    }

    public function testProcessWithDoctrineEnabled(): void
    {
        $this->container->setParameter('messagebus.listeners.doctrine', true);
        $this->container->setDefinition('doctrine', new Definition());
        $this->container->setDefinition('doctrine_mongodb', new Definition());

        $this->pass->process($this->container);

        $definitions = $this->container->getDefinitions();
        $doctrineSubscribers = array_filter($definitions, function ($definition) {
            return ConsumerDoctrineEventSubscriber::class === $definition->getClass();
        });

        $this->assertCount(2, $doctrineSubscribers);

        foreach ($doctrineSubscribers as $definition) {
            $this->assertTrue($definition->hasTag('kernel.event_subscriber'));
        }
    }

    public function testProcessWithDoctrineDisabled(): void
    {
        $this->container->setParameter('messagebus.listeners.doctrine', false);
        $this->container->setDefinition('doctrine', new Definition());

        $this->pass->process($this->container);

        $definitions = $this->container->getDefinitions();
        $doctrineSubscribers = array_filter($definitions, function ($definition) {
            return ConsumerDoctrineEventSubscriber::class === $definition->getClass();
        });

        $this->assertCount(0, $doctrineSubscribers);
    }

    public function testProcessWithoutDoctrineServices(): void
    {
        $this->container->setParameter('messagebus.listeners.doctrine', true);

        $this->pass->process($this->container);

        $definitions = $this->container->getDefinitions();
        $doctrineSubscribers = array_filter($definitions, function ($definition) {
            return ConsumerDoctrineEventSubscriber::class === $definition->getClass();
        });

        $this->assertCount(0, $doctrineSubscribers);
    }
}
