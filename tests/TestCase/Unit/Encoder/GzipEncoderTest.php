<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Encoder;

use MessageBusBundle\Encoder\GzipEncoder;
use MessageBusBundle\Tests\DataProvider\EncoderDataProvider;
use PhpSolution\FunctionalTest\TestCase\Traits\FixturesTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GzipEncoderTest extends TestCase
{
    use FixturesTrait;

    private GzipEncoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new GzipEncoder(6);
    }

    public function testGetEncoding(): void
    {
        $this->assertEquals('gzip', $this->encoder->getEncoding());
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

    #[DataProvider('providerFixtureData')]
    public function testEncodeWithFixtures(string $data): void
    {
        $encoded = $this->encoder->encode($data);
        $decoded = $this->encoder->decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    public static function providerCompressionData(): iterable
    {
        return EncoderDataProvider::compressionData();
    }

    public static function providerFixtureData(): iterable
    {
        $fixture = self::getFixturesFromJson('Encoder/compression-data.json');

        foreach ($fixture as $key => $data) {
            yield $key => [$data];
        }
    }
}
