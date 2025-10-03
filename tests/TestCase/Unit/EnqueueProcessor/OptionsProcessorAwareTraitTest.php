<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\EnqueueProcessor;

use MessageBusBundle\EnqueueProcessor\OptionsProcessorAwareTrait;
use PHPUnit\Framework\TestCase;

class OptionsProcessorAwareTraitTest extends TestCase
{
    public function testSetOptions(): void
    {
        $object = new class {
            use OptionsProcessorAwareTrait;
        };

        $options = ['key1' => 'value1', 'key2' => 'value2'];
        $object->setOptions($options);

        $this->assertEquals('value1', $object->getOption('key1'));
        $this->assertEquals('value2', $object->getOption('key2'));
    }

    public function testGetOptionNonExistent(): void
    {
        $object = new class {
            use OptionsProcessorAwareTrait;
        };

        $this->assertNull($object->getOption('non_existent'));
    }
}
