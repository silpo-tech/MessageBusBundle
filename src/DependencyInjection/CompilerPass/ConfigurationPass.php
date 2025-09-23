<?php

declare(strict_types=1);

namespace MessageBusBundle\DependencyInjection\CompilerPass;

use MessageBusBundle\Enqueue\LogExtension;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ConfigurationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $names = $container->getParameter('enqueue.transports');
        foreach ($names as $name) {
            $container->getDefinition('enqueue.transport.'.$name.'.log_extension')->setClass(LogExtension::class)
                ->setArgument('$debug', $container->getParameter('messagebus.debug'));
        }
    }
}
