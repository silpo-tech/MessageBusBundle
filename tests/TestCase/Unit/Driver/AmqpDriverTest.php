<?php

namespace MessageBusBundle\Tests\TestCase\Unit\Driver;

use MessageBusBundle\Driver\AmqpDriver;
use PHPUnit\Framework\TestCase;

class AmqpDriverTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(AmqpDriver::class));
    }

    public function testExtendsBaseAmqpDriver(): void
    {
        $reflection = new \ReflectionClass(AmqpDriver::class);
        $this->assertEquals('Enqueue\Client\Driver\AmqpDriver', $reflection->getParentClass()->getName());
    }

    public function testHasSetupBrokerMethod(): void
    {
        $reflection = new \ReflectionClass(AmqpDriver::class);
        $this->assertTrue($reflection->hasMethod('setupBroker'));

        $method = $reflection->getMethod('setupBroker');
        $this->assertTrue($method->isPublic());
    }

    public function testHasCreateRouterTopicMethod(): void
    {
        $reflection = new \ReflectionClass(AmqpDriver::class);
        $this->assertTrue($reflection->hasMethod('createRouterTopic'));

        $method = $reflection->getMethod('createRouterTopic');
        $this->assertTrue($method->isProtected());
    }
}
