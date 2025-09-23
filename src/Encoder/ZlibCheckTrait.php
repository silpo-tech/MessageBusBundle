<?php

declare(strict_types=1);

namespace MessageBusBundle\Encoder;

use MessageBusBundle\Exception\CompressException;

trait ZlibCheckTrait
{
    protected function checkZlibAvailable(): void
    {
        if (!extension_loaded('zlib')) {
            throw new CompressException();
        }
    }
}
