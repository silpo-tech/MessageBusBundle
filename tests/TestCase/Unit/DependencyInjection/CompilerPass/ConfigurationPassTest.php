<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\DependencyInjection\CompilerPass;

use MessageBusBundle\DependencyInjection\CompilerPass\ConfigurationPass;
use MessageBusBundle\Enqueue\LogExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class ConfigurationPassTest extends TestCase
{
    private ConfigurationPass $pass;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->pass = new ConfigurationPass();
        $this->container = new ContainerBuilder();
    }

    public function testProcess(): void
    {
        $this->container->setParameter('enqueue.transports', ['default', 'async']);
        $this->container->setParameter('messagebus.debug', true);

        $logExtensionDef1 = new Definition();
        $logExtensionDef2 = new Definition();

        $this->container->setDefinition('enqueue.transport.default.log_extension', $logExtensionDef1);
        $this->container->setDefinition('enqueue.transport.async.log_extension', $logExtensionDef2);

        $this->pass->process($this->container);

        $this->assertEquals(LogExtension::class, $logExtensionDef1->getClass());
        $this->assertEquals(LogExtension::class, $logExtensionDef2->getClass());
        $this->assertTrue($logExtensionDef1->getArgument('$debug'));
        $this->assertTrue($logExtensionDef2->getArgument('$debug'));
    }
}
