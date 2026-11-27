<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EnqueueProcessor\ExceptionHandler;

use MessageBusBundle\EnqueueProcessor\ExceptionHandler\NonRetryableExceptionChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NonRetryableExceptionCheckerTest extends TestCase
{
    private NonRetryableExceptionChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new NonRetryableExceptionChecker();
    }

    #[DataProvider('providerDefaultNonRetryableExceptions')]
    public function testDefaultExceptionsAreNonRetryable(\Throwable $exception): void
    {
        self::assertTrue($this->checker->isNonRetryable($exception, [
            \InvalidArgumentException::class,
            \UnexpectedValueException::class,
            \TypeError::class,
            \ValueError::class,
        ]));
    }

    public function testSubclassOfDefaultIsNonRetryable(): void
    {
        $subclass = new class extends \InvalidArgumentException {};

        self::assertTrue($this->checker->isNonRetryable($subclass, [\InvalidArgumentException::class]));
    }

    public function testRuntimeExceptionIsRetryable(): void
    {
        self::assertFalse($this->checker->isNonRetryable(new \RuntimeException()));
    }

    public function testGlobalNonRetryableExceptionIsNonRetryable(): void
    {
        $custom = new class extends \RuntimeException {};
        $checker = new NonRetryableExceptionChecker([$custom::class]);

        self::assertTrue($checker->isNonRetryable($custom));
    }

    public function testAdditionalNonRetryableExceptionIsNonRetryable(): void
    {
        $custom = new class extends \RuntimeException {};
        $checker = new NonRetryableExceptionChecker();

        self::assertTrue($checker->isNonRetryable($custom, [$custom::class]));
    }

    public function testExceptionNotInAnyListIsRetryable(): void
    {
        $checker = new NonRetryableExceptionChecker([\RuntimeException::class]);
        $other = new class extends \LogicException {};

        self::assertFalse($checker->isNonRetryable($other));
    }

    public static function providerDefaultNonRetryableExceptions(): iterable
    {
        yield 'InvalidArgumentException' => [new \InvalidArgumentException()];
        yield 'UnexpectedValueException' => [new \UnexpectedValueException()];
        yield 'TypeError' => [new \TypeError()];
        yield 'ValueError' => [new \ValueError()];
    }
}
