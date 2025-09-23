<?php

declare(strict_types=1);

namespace MessageBusBundle;

use MessageBusBundle\DependencyInjection\CompilerPass\ConfigurationPass;
use MessageBusBundle\DependencyInjection\CompilerPass\DoctrineSubscriberPass;
use MessageBusBundle\DependencyInjection\CompilerPass\EncoderProducerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class MessageBusBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new EncoderProducerPass());
        $container->addCompilerPass(new ConfigurationPass());
        $container->addCompilerPass(new DoctrineSubscriberPass());
    }
}
