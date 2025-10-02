<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\DataProvider;

class EncoderDataProvider
{
    public static function compressionData(): iterable
    {
        yield 'simple text' => [
            ['data' => 'Hello World'],
        ];

        yield 'json data' => [
            ['data' => '{"key":"value"}'],
        ];

        yield 'large text' => [
            ['data' => str_repeat('Lorem ipsum dolor sit amet. ', 100)],
        ];
    }

    public static function encodingData(): iterable
    {
        yield 'gzip encoding' => [
            ['encoding' => 'gzip'],
        ];

        yield 'zlib encoding' => [
            ['encoding' => 'zlib'],
        ];

        yield 'deflate encoding' => [
            ['encoding' => 'deflate'],
        ];
    }
}
