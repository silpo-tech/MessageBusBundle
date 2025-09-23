<?php

declare(strict_types=1);

namespace MessageBusBundle\DependencyInjection;

use MessageBusBundle\Encoder\DeflateEncoder;
use MessageBusBundle\Encoder\GzipEncoder;
use MessageBusBundle\Encoder\ZlibEncoder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const DEFAULT_COMPRESSION_LEVEL = 6;

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('message_bus');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('allow_options')->scalarPrototype()->defaultValue([])->end()->end()
                ->booleanNode('debug')->defaultFalse()->end()
                ->arrayNode('listeners')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('doctrine')
                            ->defaultValue(true)
                        ->end()
                    ->end()
                ->end() // listeners
                ->scalarNode('compression_level')->defaultValue(self::DEFAULT_COMPRESSION_LEVEL)->end()
                ->enumNode('default_encoder')
                    ->values([null, GzipEncoder::ENCODING, ZlibEncoder::ENCODING, DeflateEncoder::ENCODING])
                    ->defaultNull()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
