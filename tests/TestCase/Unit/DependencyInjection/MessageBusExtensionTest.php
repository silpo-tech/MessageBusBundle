<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\DependencyInjection;

use MessageBusBundle\DependencyInjection\Configuration;
use MessageBusBundle\DependencyInjection\MessageBusExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MessageBusExtensionTest extends TestCase
{
    private MessageBusExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new MessageBusExtension();
        $this->container = new ContainerBuilder();
    }

    public function testLoadWithDefaultConfig(): void
    {
        $configs = [[]];

        $this->extension->load($configs, $this->container);

        $this->assertFalse($this->container->getParameter('messagebus.debug'));
        $this->assertTrue($this->container->getParameter('messagebus.listeners.doctrine'));
        $this->assertEquals(Configuration::DEFAULT_COMPRESSION_LEVEL, $this->container->getParameter('messagebus.compression.level'));
        $this->assertNull($this->container->getParameter('messagebus.encoder.default'));
        $this->assertEquals([], $this->container->getParameter('messagebus.allow_options'));
    }

    public function testLoadWithCustomConfig(): void
    {
        $configs = [[
            'debug' => true,
            'listeners' => ['doctrine' => false],
            'compression_level' => 9,
            'default_encoder' => 'gzip',
            'allow_options' => ['option1', 'option2'],
        ]];

        $this->extension->load($configs, $this->container);

        $this->assertTrue($this->container->getParameter('messagebus.debug'));
        $this->assertFalse($this->container->getParameter('messagebus.listeners.doctrine'));
        $this->assertEquals(9, $this->container->getParameter('messagebus.compression.level'));
        $this->assertEquals('gzip', $this->container->getParameter('messagebus.encoder.default'));
        $this->assertEquals(['option1', 'option2'], $this->container->getParameter('messagebus.allow_options'));
    }
}
