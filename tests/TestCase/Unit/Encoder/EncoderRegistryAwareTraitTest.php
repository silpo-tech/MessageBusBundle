<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Encoder;

use MessageBusBundle\Encoder\EncoderRegistry;
use MessageBusBundle\Encoder\EncoderRegistryAwareTrait;
use PHPUnit\Framework\TestCase;

class EncoderRegistryAwareTraitTest extends TestCase
{
    public function testSetEncoderRegistry(): void
    {
        $registry = $this->createMock(EncoderRegistry::class);

        $object = new class {
            use EncoderRegistryAwareTrait;

            public function getRegistry(): EncoderRegistry
            {
                return $this->encoderRegistry;
            }
        };

        $object->setEncoderRegistry($registry);

        $this->assertSame($registry, $object->getRegistry());
    }
}
