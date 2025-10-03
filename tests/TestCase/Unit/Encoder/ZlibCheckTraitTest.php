<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Encoder;

use MessageBusBundle\Encoder\ZlibCheckTrait;
use MessageBusBundle\Exception\CompressException;
use PHPUnit\Framework\TestCase;

class ZlibCheckTraitTest extends TestCase
{
    public function testCheckZlibAvailable(): void
    {
        $object = new class {
            use ZlibCheckTrait;

            public function testCheck(): void
            {
                $this->checkZlibAvailable();
            }
        };

        if (extension_loaded('zlib')) {
            $object->testCheck();
            $this->assertTrue(true); // No exception thrown
        } else {
            $this->expectException(CompressException::class);
            $object->testCheck();
        }
    }
}
