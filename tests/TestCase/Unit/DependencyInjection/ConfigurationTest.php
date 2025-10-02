<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\DependencyInjection;

use MessageBusBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    private Configuration $configuration;
    private Processor $processor;

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

        $this->assertEquals($expected, $config);
    }

    #[DataProvider('providerCustomConfigurations')]
    public function testCustomConfiguration(array $inputConfig, array $expectedConfig): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [$inputConfig]);

        foreach ($expectedConfig as $key => $value) {
            $this->assertEquals($value, $config[$key]);
        }
    }

    #[DataProvider('providerValidEncoders')]
    public function testValidEncoders(?string $encoder): void
    {
        $configs = ['message_bus' => ['default_encoder' => $encoder]];
        $config = $this->processor->processConfiguration($this->configuration, $configs);

        $this->assertEquals($encoder, $config['default_encoder']);
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
