# Message Bus Bundle #

[![CI](https://github.com/silpo-tech/MessageBusBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/silpo-tech/MessageBusBundle/actions)
[![codecov](https://codecov.io/gh/silpo-tech/MessageBusBundle/graph/badge.svg)](https://codecov.io/gh/silpo-tech/MessageBusBundle)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Development ##

Run tests
```bash
make test # SKIP_INSTALL=true (if you have already installed dependencies)
```

To list all available commands
```bash
make help
```

## Installation ##

Require the bundle and its dependencies with composer:

```bash
$ composer require silpo-tech/message-bus-bundle
```

Register the bundle:

```php
// project/config/bundles.php

return [
    MessageBusBundle\MessageBusBundle::class => ['all' => true],
];
```

Add tags to batch processor to tell application where to look for batch processors
```yaml
    MessageBusBundle\EnqueueProcessor\Batch\BatchProcessorInterface:
      tags:
        - 'enqueue.transport.processor'
        - name: !php/const MessageBusBundle\EnqueueProcessor\Batch\AbstractBatchProcessor::BATCH_PROCESSOR_TAG
          indexed_by: key
```


Start consuming:

```bash
./bin/console enqueue:consume --setup-broker -vvv
```

How to enable messages compression:
```yaml
message_bus:
  default_encoder: gzip #available: null, gzip, zlib, deflate
```
and use MessageBusBundle\Producer\EncoderProducerInterface in your project*. Such service is not available by default.

* Do not forget to enable zlib extension at PHP.

## Retry Configuration ##

By default retry is disabled (`max_retries: 0`). Enable it globally:

```yaml
message_bus:
  requeue:
    max_retries: 5
    expose_previous_exception: true  # use previous exception message/trace in failed queue properties
    non_retryable_exceptions:        # additional project-level non-retryable exceptions
      - App\Exception\SomeBusinessException
```

Default non-retryable exceptions (defined in `AbstractProcessor::getNonRetryableExceptions()`):
- `\InvalidArgumentException`
- `\UnexpectedValueException`
- `\TypeError`
- `\ValueError`

Always non-retryable regardless of config:
- `RejectException` → `REJECT`
- `ValidationException` → `ACK`, sent to `<queue>.failed`

### Failed queues ###

| Scenario | Queue |
|---|---|
| Retries exceeded | `<queue>.failed` |
| Non-retryable exception | `<queue>.exception.failed` |

### Per-processor retry override ###

Implement `OverridesRetryConfigInterface` to override `max_retries` for a specific processor:

```php
use MessageBusBundle\EnqueueProcessor\OverridesRetryConfigInterface;

final class MyProcessor extends AbstractProcessor implements OverridesRetryConfigInterface
{
    public function getMaxRetries(): int
    {
        return 3;
    }
}
```

Priority: `RequeueException::getCount()` > `OverridesRetryConfigInterface::getMaxRetries()` > global `max_retries`.

### Per-processor non-retryable exceptions ###

Override `getNonRetryableExceptions()` in your processor to customize which exceptions should not be retried:

```php
final class MyProcessor extends AbstractProcessor
{
    public function getNonRetryableExceptions(): array
    {
        return [
            ...parent::getNonRetryableExceptions(),
            MyDomainException::class,
        ];
    }
}
```

### Failed queue message properties ###

When a message is sent to `<queue>.failed` after retries are exceeded, the following properties are attached:

| Property | Description |
|---|---|
| `requeue-reason` | Exception message |
| `requeue-count` | Number of attempts |
| `requeue-previous-exception-class` | Original exception class |
| `requeue-previous-exception-message` | Original exception message |
| `requeue-previous-exception-trace` | Original exception stack trace |
