<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Enqueue;

use MessageBusBundle\Enqueue\ContainerBatchProcessorRegistry;
use MessageBusBundle\EnqueueProcessor\Batch\BatchProcessorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class ContainerBatchProcessorRegistryTest extends TestCase
{
    private ContainerInterface|MockObject $locator;
    private ContainerBatchProcessorRegistry $registry;

    protected function setUp(): void
    {
        $this->locator = $this->createMock(ContainerInterface::class);
        $this->registry = new ContainerBatchProcessorRegistry($this->locator);
    }

    public function testGetExistingProcessor(): void
    {
        $processor = $this->createMock(BatchProcessorInterface::class);

        $this->locator->expects($this->once())
            ->method('has')
            ->with('test_processor')
            ->willReturn(true);

        $this->locator->expects($this->once())
            ->method('get')
            ->with('test_processor')
            ->willReturn($processor);

        $result = $this->registry->get('test_processor');

        $this->assertSame($processor, $result);
    }

    public function testGetNonExistingProcessor(): void
    {
        $this->locator->expects($this->once())
            ->method('has')
            ->with('non_existing')
            ->willReturn(false);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Service locator does not have a processor with name "non_existing".');

        $this->registry->get('non_existing');
    }
}
