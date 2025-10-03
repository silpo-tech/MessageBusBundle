<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Tests;

use MessageBusBundle\Producer\ProducerInterface;
use MessageBusBundle\Producer\StubProducer;
use MessageBusBundle\Tests\MessageBusTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class MessageBusTraitTest extends TestCase
{
    use ProphecyTrait;

    private ContainerInterface $container;
    private StubProducer $producer;
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->producer = new StubProducer();
        $this->serializer = $this->createMock(SerializerInterface::class);
    }

    public function testAssertSingleMessage(): void
    {
        $testObject = new class($this->container, $this->producer) extends TestCase {
            use MessageBusTrait;

            private ContainerInterface $container;
            private StubProducer $producer;

            public function __construct(ContainerInterface $container, StubProducer $producer)
            {
                parent::__construct('testDummy');
                $this->container = $container;
                $this->producer = $producer;
            }

            public static function getContainer(): ContainerInterface
            {
                return self::$instance->container;
            }

            private static $instance;

            public function setInstance(): void
            {
                self::$instance = $this;
            }
        };

        $testObject->setInstance();

        $this->container->method('get')->with(ProducerInterface::class)->willReturn($this->producer);

        $expectedMessage = ['id' => 123, 'name' => 'test'];
        $this->producer->send('test.topic', json_encode($expectedMessage));

        $testObject::assertSingleMessage($expectedMessage, 'test.topic');

        $this->assertTrue(true); // Assertion passed without exception
    }

    public function testGetMessageBusProducer(): void
    {
        $testObject = new class($this->container, $this->producer) {
            use MessageBusTrait;

            private ContainerInterface $container;
            private StubProducer $producer;

            public function __construct(ContainerInterface $container, StubProducer $producer)
            {
                $this->container = $container;
                $this->producer = $producer;
            }

            public static function getContainer(): ContainerInterface
            {
                return self::$instance->container;
            }

            private static $instance;

            public function setInstance(): void
            {
                self::$instance = $this;
            }
        };

        $testObject->setInstance();

        $this->container->method('get')->with(ProducerInterface::class)->willReturn($this->producer);

        $result = $testObject::getMessageBusProducer();

        $this->assertSame($this->producer, $result);
    }
}
