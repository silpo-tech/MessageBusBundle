<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests;

use Enqueue\Bundle\EnqueueBundle;
use MessageBusBundle\MessageBusBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new EnqueueBundle();
        yield new MessageBusBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
        ]);

        $container->extension('message_bus', []);

        $container->extension('enqueue', [
            'default' => [
                'transport' => [
                    'dsn' => '%env(MESSAGE_BUS_DSN)%',
                ],
                'client' => [
                    'app_name' => '%env(APP_NAME)%',
                ],
            ],
        ]);
    }
}
