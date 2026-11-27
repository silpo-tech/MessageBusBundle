<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Service;

use Enqueue\AmqpLib\AmqpContext;
use Interop\Queue\Context;
use MessageBusBundle\Service\ConsumerHeartbeat;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConsumerHeartbeatTest extends TestCase
{
    #[DataProvider('beatProvider')]
    public function testBeat(array $actions, int $expectedChecks): void
    {
        $connection = $this->createMock(AbstractConnection::class);
        $connection->expects($this->exactly($expectedChecks))->method('checkHeartBeat');

        $context = $this->createAmqpContext($connection);
        $heartbeat = new ConsumerHeartbeat();

        foreach ($actions as $action) {
            match ($action) {
                'attach' => $heartbeat->attach($context),
                'detach' => $heartbeat->detach(),
                'beat' => $heartbeat->beat(),
                default => throw new \InvalidArgumentException(sprintf('Unknown action "%s"', $action)),
            };
        }
    }

    public static function beatProvider(): iterable
    {
        yield 'not attached: nothing is checked' => [
            'actions' => ['beat', 'beat'],
            'expectedChecks' => 0,
        ];

        yield 'attached: first beat checks the connection' => [
            'actions' => ['attach', 'beat'],
            'expectedChecks' => 1,
        ];

        yield 'attached: beats within the interval check the connection once' => [
            'actions' => ['attach', 'beat', 'beat', 'beat'],
            'expectedChecks' => 1,
        ];

        yield 'detached: beat does nothing' => [
            'actions' => ['attach', 'detach', 'beat'],
            'expectedChecks' => 0,
        ];

        yield 're-attached: interval is reset, so the next beat checks again' => [
            'actions' => ['attach', 'beat', 'attach', 'beat'],
            'expectedChecks' => 2,
        ];
    }

    public function testNotAmqpContextIsIgnored(): void
    {
        $heartbeat = new ConsumerHeartbeat();
        $heartbeat->attach($this->createMock(Context::class));

        $this->expectNotToPerformAssertions();

        $heartbeat->beat();
    }

    public function testWrapYieldsEveryItemWithKeysAndBeatsOnce(): void
    {
        $connection = $this->createMock(AbstractConnection::class);
        $connection->expects($this->once())->method('checkHeartBeat');

        $heartbeat = new ConsumerHeartbeat();
        $heartbeat->attach($this->createAmqpContext($connection));

        $result = iterator_to_array($heartbeat->wrap(['a' => 1, 'b' => 2, 'c' => 3]));

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $result);
    }

    private function createAmqpContext(AbstractConnection $connection): AmqpContext
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('getConnection')->willReturn($connection);

        $context = $this->createMock(AmqpContext::class);
        $context->method('getLibChannel')->willReturn($channel);

        return $context;
    }
}
