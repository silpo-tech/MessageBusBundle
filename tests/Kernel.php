<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests;

use Enqueue\Bundle\EnqueueBundle;
use MessageBusBundle\MessageBusBundle;
use MessageBusBundle\Tests\Stub\Processor\NonAbstractBatchProcessor;
use MessageBusBundle\Tests\Stub\Processor\TestBatchOptionsProcessor;
use MessageBusBundle\Tests\Stub\Processor\TestBatchProcessor;
use MessageBusBundle\Tests\Stub\Processor\TestOptionsProcessor;
use MessageBusBundle\Tests\Stub\Processor\TestProcessor;
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

    // @phpstan-ignore-next-line
    private function configureContainer(ContainerConfigurator $container): void
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

        $container->services()
            ->set(TestBatchProcessor::class)
            ->tag('messagesbus.batch_processor', ['key' => 'test.batch.processor'])
            ->public();

        $container->services()
            ->set(TestBatchOptionsProcessor::class)
            ->tag('messagesbus.batch_processor', ['key' => 'test.batch.options.processor'])
            ->public();

        $container->services()
            ->set(NonAbstractBatchProcessor::class)
            ->tag('messagesbus.batch_processor', ['key' => 'test.non.abstract.batch.processor'])
            ->public();

        $container->services()
            ->set(TestProcessor::class)
            ->tag('enqueue.transport.processor', ['processor' => 'test.processor'])
            ->public();

        $container->services()
            ->set(TestOptionsProcessor::class)
            ->tag('enqueue.transport.processor', ['processor' => 'test.options.processor'])
            ->public();
    }
}
