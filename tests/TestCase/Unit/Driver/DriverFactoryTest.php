<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Driver;

use Enqueue\Client\Config;
use Enqueue\Client\DriverInterface;
use Enqueue\Client\RouteCollection;
use Interop\Queue\ConnectionFactory;
use MessageBusBundle\Driver\AmqpDriver;
use MessageBusBundle\Driver\DriverFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DriverFactoryTest extends TestCase
{
    private DriverFactory $factory;
    private ConnectionFactory|MockObject $connectionFactory;
    private Config|MockObject $config;
    private RouteCollection|MockObject $routeCollection;

    protected function setUp(): void
    {
        $this->factory = new DriverFactory();
        $this->connectionFactory = $this->createMock(ConnectionFactory::class);
        $this->config = $this->createMock(Config::class);
        $this->routeCollection = $this->createMock(RouteCollection::class);
    }

    public function testCreateAmqpDriver(): void
    {
        $context = $this->createMock(\Interop\Amqp\AmqpContext::class);

        $this->config->expects($this->once())
            ->method('getTransportOption')
            ->with('dsn')
            ->willReturn('amqp://localhost');

        $this->connectionFactory->expects($this->once())
            ->method('createContext')
            ->willReturn($context);

        $result = $this->factory->create($this->connectionFactory, $this->config, $this->routeCollection);

        $this->assertInstanceOf(AmqpDriver::class, $result);
    }

    public function testCreateFallbackDriver(): void
    {
        $this->config->expects($this->exactly(2))
            ->method('getTransportOption')
            ->with('dsn')
            ->willReturn('null://');

        $result = $this->factory->create($this->connectionFactory, $this->config, $this->routeCollection);

        $this->assertInstanceOf(DriverInterface::class, $result);
    }
}
