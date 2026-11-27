<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Messenger;

use Interop\Queue\Processor;
use MessageBusBundle\Messenger\Bridge\ProcessorRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class ProcessorRegistryTest extends TestCase
{
    public function testItReturnsTheProcessorByServiceId(): void
    {
        $processor = $this->createMock(Processor::class);

        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->with('proc.id')->willReturn(true);
        $locator->method('get')->with('proc.id')->willReturn($processor);

        $registry = new ProcessorRegistry($locator);

        self::assertSame($processor, $registry->get('proc.id'));
    }

    public function testItThrowsWhenProcessorIsUnknown(): void
    {
        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->willReturn(false);

        $registry = new ProcessorRegistry($locator);

        $this->expectException(\InvalidArgumentException::class);
        $registry->get('missing.id');
    }

    public function testItThrowsWhenServiceIsNotAProcessor(): void
    {
        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturn(new \stdClass());

        $registry = new ProcessorRegistry($locator);

        $this->expectException(\LogicException::class);
        $registry->get('wrong.id');
    }
}
