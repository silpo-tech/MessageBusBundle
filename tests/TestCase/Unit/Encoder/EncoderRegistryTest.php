<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Encoder;

use MessageBusBundle\Encoder\EncoderInterface;
use MessageBusBundle\Encoder\EncoderRegistry;
use MessageBusBundle\Tests\DataProvider\EncoderDataProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EncoderRegistryTest extends TestCase
{
    private EncoderRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new EncoderRegistry();
    }

    #[DataProvider('providerEncoderData')]
    public function testAddAndGetEncoder(array $data): void
    {
        $encoder = $this->createMock(EncoderInterface::class);
        $encoder->method('getEncoding')->willReturn($data['encoding']);

        $this->registry->addEncoder($encoder);

        $result = $this->registry->getEncoder($data['encoding']);

        $this->assertSame($encoder, $result);
    }

    public function testGetEncoderThrowsExceptionForInvalidEncoding(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid encoding');

        $this->registry->getEncoder('nonexistent');
    }

    public static function providerEncoderData(): iterable
    {
        return EncoderDataProvider::encodingData();
    }
}
