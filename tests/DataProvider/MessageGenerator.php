<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\DataProvider;

use PhpAmqpLib\Message\AMQPMessage;
use PhpSolution\FunctionalTest\TestCase\Traits\FixturesTrait;

class MessageGenerator
{
    use FixturesTrait;
    public static function generateMessages(int $messagesQty = 1): array
    {
        $messages = [];
        for ($i = 0; $i < $messagesQty; $i++) {
            $correlationId = (string)uuid_create();
            $messageBody = json_encode(self::getFixturesFromJson('messageBody.json'));
            $messageHeaders = self::getFixturesFromJson('messageHeaders.json');

            $properties = [
                'correlation_id' => $correlationId,
                'application_headers' => $messageHeaders,
            ];

            $message = new AmqpMessage($messageBody, $properties);
            $message->setDeliveryInfo(1, true, '', 'route-name');

            $messages[$correlationId] = $message;
        }

        return $messages;
    }
}