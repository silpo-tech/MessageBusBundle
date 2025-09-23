<?php

declare(strict_types=1);

namespace MessageBusBundle\Encoder;

class DeflateEncoder implements EncoderInterface
{
    use ZlibCheckTrait;

    public const ENCODING = 'deflate';

    private int $compressionLevel;

    public function __construct(int $compressionLevel)
    {
        $this->compressionLevel = $compressionLevel;
    }

    public function encode(string $data): ?string
    {
        $this->checkZlibAvailable();

        $value = gzdeflate($data, $this->compressionLevel);

        return $value ?: null;
    }

    public function decode(string $data): ?string
    {
        $this->checkZlibAvailable();

        $value = gzinflate($data);

        return $value ?: null;
    }

    public function getEncoding(): string
    {
        return self::ENCODING;
    }
}
