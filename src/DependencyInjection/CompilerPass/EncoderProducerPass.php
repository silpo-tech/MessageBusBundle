<?php

declare(strict_types=1);

namespace MessageBusBundle\DependencyInjection\CompilerPass;

use MessageBusBundle\Encoder\EncoderInterface;
use MessageBusBundle\Encoder\EncoderRegistry;
use MessageBusBundle\Producer\EncoderProducer;
use MessageBusBundle\Producer\EncoderProducerInterface;
use MessageBusBundle\Producer\ProducerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class EncoderProducerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $defaultEncoder = $container->getParameter('messagebus.encoder.default');
        if (null !== $defaultEncoder) {
            $encoderRegistryDefinition = $container->getDefinition(EncoderRegistry::class);

            $defaultEncoderDefinition = new Definition();
            $defaultEncoderDefinition->setClass(EncoderInterface::class)
                ->setFactory([$encoderRegistryDefinition, 'getEncoder'])
                ->setArguments([$defaultEncoder])
            ;

            $encoderProducerDefinition = new Definition();
            $encoderProducerDefinition->setClass(EncoderProducer::class)
                ->setArguments([
                    $container->getDefinition(ProducerInterface::class),
                    $defaultEncoderDefinition,
                ])
            ;

            $container->setDefinition(EncoderProducerInterface::class, $encoderProducerDefinition);
        }
    }
}
