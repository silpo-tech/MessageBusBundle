<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\DataProvider;

use PhpAmqpLib\Message\AMQPMessage;
use PhpSolution\FunctionalTest\TestCase\Traits\FixturesTrait;

class FailedMessageGenerator
{
    use FixturesTrait;

    public static function generateMessages(int $messagesQty = 1): array
    {
        $messages = [];

        for ($i = 0; $i < $messagesQty; ++$i) {
            $messageBody = json_encode(self::getFixturesFromJson('messageBody.json'));
            $correlationId = (string) uuid_create();
            $messageHeaders = self::getFixturesFromJson('messageHeaders.json');
            $properties = [
                'correlation_id' => $correlationId,
                'application_headers' => $messageHeaders,
            ];

            $message = new AMQPMessage($messageBody, $properties);
            $message->setDeliveryInfo(1, true, '', 'route-name');

            $messages[$correlationId] = $message;
        }

        return $messages;
    }
}
