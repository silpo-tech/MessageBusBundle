<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\DependencyInjection\CompilerPass;

use MessageBusBundle\DependencyInjection\CompilerPass\EncoderProducerPass;
use MessageBusBundle\Encoder\EncoderRegistry;
use MessageBusBundle\Producer\EncoderProducer;
use MessageBusBundle\Producer\EncoderProducerInterface;
use MessageBusBundle\Producer\ProducerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class EncoderProducerPassTest extends TestCase
{
    private EncoderProducerPass $pass;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->pass = new EncoderProducerPass();
        $this->container = new ContainerBuilder();
    }

    public function testProcessWithDefaultEncoder(): void
    {
        $this->container->setParameter('messagebus.encoder.default', 'gzip');

        $encoderRegistryDef = new Definition(EncoderRegistry::class);
        $producerDef = new Definition();

        $this->container->setDefinition(EncoderRegistry::class, $encoderRegistryDef);
        $this->container->setDefinition(ProducerInterface::class, $producerDef);

        $this->pass->process($this->container);

        $this->assertTrue($this->container->hasDefinition(EncoderProducerInterface::class));
        $encoderProducerDef = $this->container->getDefinition(EncoderProducerInterface::class);
        $this->assertEquals(EncoderProducer::class, $encoderProducerDef->getClass());
    }

    public function testProcessWithoutDefaultEncoder(): void
    {
        $this->container->setParameter('messagebus.encoder.default', null);

        $this->pass->process($this->container);

        $this->assertFalse($this->container->hasDefinition(EncoderProducerInterface::class));
    }
}
