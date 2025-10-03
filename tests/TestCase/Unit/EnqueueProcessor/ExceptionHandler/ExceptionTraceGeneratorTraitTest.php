<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EnqueueProcessor\ExceptionHandler;

use MessageBusBundle\EnqueueProcessor\ExceptionHandler\ExceptionTraceGeneratorTrait;
use PHPUnit\Framework\TestCase;

class ExceptionTraceGeneratorTraitTest extends TestCase
{
    public function testGetExceptionTraceTrait(): void
    {
        $object = new class {
            use ExceptionTraceGeneratorTrait;

            public function testTrace(\Throwable $exception): array
            {
                return $this->getExceptionTraceTrait($exception);
            }
        };

        $exception = new \Exception('Test exception');
        $trace = $object->testTrace($exception);

        $this->assertIsArray($trace);
        $this->assertLessThanOrEqual(5, count($trace));
    }

    public function testGetExceptionTraceTraitWithDepth(): void
    {
        $object = new class {
            use ExceptionTraceGeneratorTrait;

            public function testTrace(\Throwable $exception, int $depth): array
            {
                return $this->getExceptionTraceTrait($exception, $depth);
            }
        };

        $exception = new \Exception('Test exception');
        $trace = $object->testTrace($exception, 2);

        $this->assertIsArray($trace);
        $this->assertLessThanOrEqual(2, count($trace));
    }
}
