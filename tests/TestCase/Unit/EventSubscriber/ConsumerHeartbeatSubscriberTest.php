<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EventSubscriber;

use Enqueue\AmqpLib\AmqpContext;
use Interop\Queue\Message;
use MessageBusBundle\Events;
use MessageBusBundle\Events\ConsumeEvent;
use MessageBusBundle\Events\PreConsumeEvent;
use MessageBusBundle\EventSubscriber\ConsumerHeartbeatSubscriber;
use MessageBusBundle\Service\ConsumerHeartbeat;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PHPUnit\Framework\TestCase;

class ConsumerHeartbeatSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        $this->assertSame(
            [
                Events::CONSUME__PRE_START => 'onStart',
                Events::CONSUME__FINISHED => 'onFinish',
                Events::CONSUME__EXCEPTION => 'onFinish',
            ],
            ConsumerHeartbeatSubscriber::getSubscribedEvents(),
        );
    }

    public function testHeartbeatIsAttachedOnStartAndDetachedOnFinish(): void
    {
        $connection = $this->createMock(AbstractConnection::class);
        $connection->expects($this->once())->method('checkHeartBeat');

        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('getConnection')->willReturn($connection);

        $context = $this->createMock(AmqpContext::class);
        $context->method('getLibChannel')->willReturn($channel);

        $message = $this->createMock(Message::class);
        $heartbeat = new ConsumerHeartbeat();
        $subscriber = new ConsumerHeartbeatSubscriber($heartbeat);

        $subscriber->onStart(new PreConsumeEvent($message, $context, self::class));
        $heartbeat->beat();

        $subscriber->onFinish(new ConsumeEvent([], $message, $context, self::class));
        $heartbeat->beat();
    }
}
