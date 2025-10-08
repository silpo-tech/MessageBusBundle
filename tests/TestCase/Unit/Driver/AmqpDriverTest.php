<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Driver;

use Enqueue\Client\Config;
use Enqueue\Client\Route;
use Enqueue\Client\RouteCollection;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use MessageBusBundle\Driver\AmqpDriver;
use PHPUnit\Framework\TestCase;

class AmqpDriverTest extends TestCase
{
    private const ROUTE_1 = 'route1';
    private const ROUTE_2 = 'route2';

    public function testSetupBrokerBindsRouterTopicToQueue(): void
    {
        $context = $this->createMock(AmqpContext::class);

        $config = $this->createMock(Config::class);
        $config->method('getRouterQueue')->willReturn('router_queue');
        $config->method('getRouterTopic')->willReturn('router_topic');

        $routeCollection = $this->createMock(RouteCollection::class);
        $routeCollection->method('all')->willReturn([
            new Route(self::ROUTE_1, 'source1', 'processor1'),
            new Route(self::ROUTE_2, 'source2', 'processor2'),
        ]);

        $routerTopic = $this->createMock(AmqpTopic::class);
        $routerQueue = $this->createMock(AmqpQueue::class);

        $context->method('createTopic')->willReturn($routerTopic);
        $context->method('createQueue')->willReturn($routerQueue);

        $routerTopic->expects($this->atLeastOnce())
            ->method('setType')
            ->with($this->logicalOr(AmqpTopic::TYPE_FANOUT, AmqpTopic::TYPE_DIRECT));

        $expectedKeys = ['', self::ROUTE_1, self::ROUTE_2];
        $seenKeys = [];

        $context->expects($this->atLeastOnce())
            ->method('bind')
            ->with($this->isInstanceOf(AmqpBind::class))
            ->willReturnCallback(function (AmqpBind $bind) use ($routerTopic, $routerQueue, &$seenKeys) {
                $this->assertSame($routerTopic, $bind->getTarget());
                $this->assertSame($routerQueue, $bind->getSource());

                $seenKeys[] = $bind->getRoutingKey();
            });

        $driver = new AmqpDriver($context, $config, $routeCollection);
        $driver->setupBroker();

        $this->assertCount(count($expectedKeys), $seenKeys);
        $this->assertEqualsCanonicalizing($expectedKeys, $seenKeys);
    }
}
