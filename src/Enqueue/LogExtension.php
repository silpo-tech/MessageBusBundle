<?php

namespace MessageBusBundle\Enqueue;

use Enqueue\Consumption\Context\MessageReceived;
use Enqueue\Consumption\Context\PostMessageReceived;
use Enqueue\Consumption\MessageReceivedExtensionInterface;
use Enqueue\Consumption\PostMessageReceivedExtensionInterface;
use Enqueue\Consumption\Result;
use Enqueue\Util\Stringify;

class LogExtension implements MessageReceivedExtensionInterface, PostMessageReceivedExtensionInterface
{
    private bool $debug = false;

    public function __construct(bool $debug)
    {
        $this->debug = $debug;
    }

    public function onMessageReceived(MessageReceived $context): void
    {
        if (true !== $this->debug) {
            return;
        }

        $message = $context->getMessage();
        $context->getLogger()->debug("[messagebus] received from {queueName}\t{body}", [
            'queueName' => $context->getConsumer()->getQueue()->getQueueName(),
            'redelivered' => $message->isRedelivered(),
            'body' => Stringify::that($message->getBody()),
            'properties' => Stringify::that($message->getProperties()),
            'headers' => Stringify::that($message->getHeaders()),
        ]);
    }

    public function onPostMessageReceived(PostMessageReceived $context): void
    {
        if (true !== $this->debug) {
            return;
        }

        $message = $context->getMessage();
        $queue = $context->getConsumer()->getQueue();
        $result = $context->getResult();

        $reason = '';
        $logMessage = "[messagebus] processed from {queueName}\t{body}\t{result}";
        if ($result instanceof Result && $result->getReason()) {
            $reason = $result->getReason();
            $logMessage .= ' {reason}';
        }
        $logContext = [
            'result' => str_replace('enqueue.', '', $result),
            'reason' => $reason,
            'queueName' => $queue->getQueueName(),
            'body' => Stringify::that($message->getBody()),
            'properties' => Stringify::that($message->getProperties()),
            'headers' => Stringify::that($message->getHeaders()),
        ];

        $context->getLogger()->debug($logMessage, $logContext);
    }
}
