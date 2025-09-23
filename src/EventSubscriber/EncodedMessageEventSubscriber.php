<?php

declare(strict_types=1);

namespace MessageBusBundle\EventSubscriber;

use MessageBusBundle\Encoder\EncoderRegistry;
use MessageBusBundle\Events;
use MessageBusBundle\MessageBus;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Interop\Queue\Message;

class EncodedMessageEventSubscriber implements EventSubscriberInterface
{
    protected EncoderRegistry $encoderRegistry;

    /**
     * PreConsumeEventSubscriber constructor.
     * @param EncoderRegistry $encoderRegistry
     */
    public function __construct(EncoderRegistry $encoderRegistry)
    {
        $this->encoderRegistry = $encoderRegistry;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::CONSUME__PRE_START => 'decodeMessage',
            Events::BATCH_CONSUME__START => 'decodeMessages',
        ];
    }

    public function decodeMessage(Events\PreConsumeEvent $event)
    {
        $this->processMessage($event->getMessage());
    }

    public function decodeMessages(Events\BatchConsumeEvent $event)
    {
        foreach ($event->getMessagesBatch() as $message) {
            $this->processMessage($message);
        }
    }

    private function processMessage(Message $message)
    {
        if ($encoding = $message->getProperty(MessageBus::ENCODING_HEADER)) {
            $encoder = $this->encoderRegistry->getEncoder($encoding);
            $decodedBody = $encoder->decode($message->getBody());
            $message->setBody($decodedBody);
            $message->setProperty(MessageBus::ENCODING_HEADER, null);
        }
    }

}
