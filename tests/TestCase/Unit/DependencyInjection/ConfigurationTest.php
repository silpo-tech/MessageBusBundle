<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\DependencyInjection;

use MessageBusBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    private Configuration $configuration
    ;
    private Processor $processor
    ;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();

        $this->processor = new Processor();
    }

    public function testDefaultConfiguration(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, []);

        $expected = [
            'allow_options' => [],
            'debug' => false,
            'listeners' => ['doctrine' => true],
            'compression_level' => Configuration::DEFAULT_COMPRESSION_LEVEL,
            'default_encoder' => null,
        ];

        self::assertSame($expected, $config);
    }

    #[DataProvider('providerCustomConfigurations')]
    public function testCustomConfiguration(array $inputConfig, array $expectedConfig): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [$inputConfig]);

        foreach ($expectedConfig as $key => $value) {
            self::assertArrayHasKey($key, $config);

            self::assertSame($value, $config[$key]);
        }
    }

    #[DataProvider('providerValidEncoders')]
    public function testValidEncoders(?string $encoder): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [[
            'default_encoder' => $encoder,
        ]]);

        self::assertSame($encoder, $config['default_encoder']);
    }

    public function testInvalidEncoder(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processor->processConfiguration($this->configuration, [[
            'default_encoder' => 'invalid_codec',
        ]]);
    }

    public function testListenersPartialMerge(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [[
            'listeners' => ['doctrine' => false],
        ]]);

        self::assertSame(['doctrine' => false], $config['listeners']);
    }

    public static function providerCustomConfigurations(): iterable
    {
        yield 'debug enabled' => [
            ['debug' => true],
            ['debug' => true],
        ];

        yield 'custom compression level' => [
            ['compression_level' => 9],
            ['compression_level' => 9],
        ];

        yield 'doctrine listener disabled' => [
            ['listeners' => ['doctrine' => false]],
            ['listeners' => ['doctrine' => false]],
        ];

        yield 'allow options set' => [
            ['allow_options' => ['option1', 'option2']],
            ['allow_options' => ['option1', 'option2']],
        ];
    }

    public static function providerValidEncoders(): iterable
    {
        yield 'null encoder' => [null];
        yield 'gzip encoder' => ['gzip'];
        yield 'zlib encoder' => ['zlib'];
        yield 'deflate encoder' => ['deflate'];
    }
}
