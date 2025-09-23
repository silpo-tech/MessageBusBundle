<?php

declare(strict_types=1);

namespace MessageBusBundle\Exception;

use RuntimeException;
use Throwable;

class CompressException extends RuntimeException
{
    public function __construct(
        $message = 'zlib extension required for messages compression and decompression.',
        $code = 0,
        Throwable|null $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
