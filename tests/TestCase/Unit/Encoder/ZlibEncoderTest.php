<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Encoder;

use MessageBusBundle\Encoder\ZlibEncoder;
use MessageBusBundle\Tests\DataProvider\EncoderDataProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ZlibEncoderTest extends TestCase
{
    private ZlibEncoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new ZlibEncoder(6);
    }

    public function testGetEncoding(): void
    {
        $this->assertEquals('zlib', $this->encoder->getEncoding());
    }

    #[DataProvider('providerCompressionData')]
    public function testEncodeAndDecode(array $data): void
    {
        $testData = $data['data'];

        $encoded = $this->encoder->encode($testData);
        $this->assertNotNull($encoded);
        $this->assertNotEquals($testData, $encoded);

        $decoded = $this->encoder->decode($encoded);
        $this->assertEquals($testData, $decoded);
    }

    public static function providerCompressionData(): iterable
    {
        return EncoderDataProvider::compressionData();
    }
}
