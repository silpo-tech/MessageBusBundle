<?php

declare(strict_types=1);

namespace MessageBusBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class MessageBusExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('enqueue_services.yaml');

        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->setParameter('messagebus.debug', $config['debug']);
        $container->setParameter('messagebus.listeners.doctrine', $config['listeners']['doctrine']);
        $container->setParameter(
            'messagebus.compression.level',
            $config['compression_level'] ?? Configuration::DEFAULT_COMPRESSION_LEVEL
        );
        $container->setParameter(
            'messagebus.encoder.default',
            $config['default_encoder'] ?? null
        );
        $container->setParameter('messagebus.allow_options', $config['allow_options'] ?? []);
    }
}
