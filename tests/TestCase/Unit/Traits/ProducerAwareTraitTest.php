<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Traits;

use MessageBusBundle\Producer\ProducerInterface;
use MessageBusBundle\Traits\ProducerAwareTrait;
use PHPUnit\Framework\TestCase;

class ProducerAwareTraitTest extends TestCase
{
    public function testSetProducer(): void
    {
        $producer = $this->createMock(ProducerInterface::class);

        $object = new class {
            use ProducerAwareTrait;

            public function getProducer(): ProducerInterface
            {
                return $this->producer;
            }
        };

        $object->setProducer($producer);

        $this->assertSame($producer, $object->getProducer());
    }
}
