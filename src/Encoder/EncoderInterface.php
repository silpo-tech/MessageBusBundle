<?php

declare(strict_types=1);

namespace MessageBusBundle\Encoder;

interface EncoderInterface
{
    public function encode(string $value): ?string;

    public function decode(string $value): ?string;

    public function getEncoding(): string;
}
