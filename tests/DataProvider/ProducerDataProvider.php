<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\DataProvider;

class ProducerDataProvider
{
    public static function sendData(): iterable
    {
        yield 'basic send' => [[
            'topic' => 'test-topic',
            'message' => 'test message',
            'headers' => [],
            'delay' => 0,
            'exchange' => 'default',
        ]];

        yield 'with custom headers' => [[
            'topic' => 'custom-topic',
            'message' => 'test message',
            'headers' => ['x-custom' => 'value'],
            'delay' => 0,
            'exchange' => 'default',
        ]];
    }

    public static function queueData(): iterable
    {
        yield 'queue message' => [[
            'queue' => 'test-queue',
            'message' => 'queue message',
            'headers' => [],
            'delay' => 0,
        ]];
    }

    public static function messageData(): iterable
    {
        yield 'basic message' => [[
            'topic' => 'test-topic',
            'message' => 'test message',
            'headers' => [],
            'exchange' => 'default',
            'delay' => 0,
        ]];

        yield 'with custom exchange' => [[
            'topic' => 'test-topic',
            'message' => 'test message',
            'headers' => [],
            'exchange' => 'custom-exchange',
            'delay' => 60,
        ]];
    }
}
