<?php

declare(strict_types=1);

namespace MessageBusBundle\DependencyInjection\CompilerPass;

use MessageBusBundle\EventSubscriber\ConsumerDoctrineEventSubscriber;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class DoctrineSubscriberPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $isDoctrineClear = $container->getParameter('messagebus.listeners.doctrine');
        if ($isDoctrineClear) {
            foreach (['doctrine_mongodb', 'doctrine'] as $definitionId) {
                if ($container->hasDefinition($definitionId)) {
                    $definition = new Definition(ConsumerDoctrineEventSubscriber::class);
                    $definition->addTag('kernel.event_subscriber');
                    $definition->setArguments([new Reference($definitionId)]);
                    $container->setDefinition(
                        uniqid('doctrine.clear.listener.' . $definitionId . '.', true),
                        $definition
                    );
                }
            }
        }
    }
}
