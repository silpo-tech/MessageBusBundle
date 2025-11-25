# Queue Types

The MessageBusBundle supports different RabbitMQ queue types through the `QueueType` enum.

## Available Queue Types

### Default Queue
Standard RabbitMQ queue with classic mirroring support.

```php
use MessageBusBundle\AmqpTools\QueueType;

public function getQueueType(): QueueType
{
    return QueueType::DEFAULT;
}
```

### Quorum Queue
RabbitMQ quorum queues provide high availability and data safety through Raft consensus algorithm.

```php
use MessageBusBundle\AmqpTools\QueueType;

public function getQueueType(): QueueType
{
    return QueueType::QUORUM;
}
```

## Usage

### 1. Define Queue Type in Processor

All processors implementing `ProcessorInterface` must define their queue type:

```php
<?php

namespace App\Processor;

use MessageBusBundle\AmqpTools\QueueType;
use MessageBusBundle\EnqueueProcessor\AbstractProcessor;

class MyProcessor extends AbstractProcessor
{
    public function getSubscribedRoutingKeys(): array
    {
        return [
            'my.queue' => ['my.routing.key'],
        ];
    }

    public function getQueueType(): QueueType
    {
        return QueueType::QUORUM; // or QueueType::DEFAULT
    }

    public function doProcess($body, \Interop\Queue\Message $message, \Interop\Queue\Context $session): string
    {
        // Process message
        return self::ACK;
    }
}
```

### 2. Setup Queues

Run the setup command to create queues with the specified type:

```bash
./bin/console messagebus:setup
```

This will create all queues defined in your processors with their respective queue types.

## Default Behavior

- `AbstractProcessor` returns `QueueType::DEFAULT` by default
- `AbstractBatchProcessor` returns `QueueType::DEFAULT` by default
- If you don't override `getQueueType()`, your queues will be created as default queues

## Quorum Queue Benefits

- **High Availability**: Data is replicated across multiple nodes
- **Data Safety**: Uses Raft consensus for replication
- **Poison Message Handling**: Built-in dead letter queue support
- **Better Performance**: Optimized for high throughput

## Quorum Queue Considerations

- Requires at least 3 RabbitMQ nodes for optimal HA
- Slightly higher latency compared to classic queues
- Cannot be non-durable
- Priority queues are not supported

## Example: Mixed Queue Types

```php
// High-priority processor with default queue
class OrderProcessor extends AbstractProcessor
{
    public function getQueueType(): QueueType
    {
        return QueueType::DEFAULT;
    }
}

// Critical processor with quorum queue for data safety
class PaymentProcessor extends AbstractProcessor
{
    public function getQueueType(): QueueType
    {
        return QueueType::QUORUM;
    }
}
```

## Testing

When testing processors, mock the `getQueueType()` method:

```php
$processor = $this->createMock(ProcessorInterface::class);
$processor->method('getQueueType')->willReturn(QueueType::QUORUM);
```

## RabbitMQ Configuration

Quorum queues are created with the following arguments:

```php
[
    'x-queue-type' => 'quorum'
]
```

All queues (both default and quorum) are created as durable.
