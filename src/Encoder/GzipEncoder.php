<?php

declare(strict_types=1);

namespace MessageBusBundle\Encoder;

class GzipEncoder implements EncoderInterface
{
    use ZlibCheckTrait;

    public const ENCODING = 'gzip';

    private int $compressionLevel;

    public function __construct(int $compressionLevel)
    {
        $this->compressionLevel = $compressionLevel;
    }

    public function encode(string $data): ?string
    {
        $this->checkZlibAvailable();

        $value = gzencode($data, $this->compressionLevel);

        return $value ?: null;
    }

    public function decode(string $data): ?string
    {
        $this->checkZlibAvailable();

        $value = gzdecode($data);

        return $value ?: null;
    }

    public function getEncoding(): string
    {
        return self::ENCODING;
    }
}
