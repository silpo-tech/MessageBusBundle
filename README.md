# Message Bus Bundle #

[![CI](https://github.com/silpo-tech/MessageBusBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/silpo-tech/MessageBusBundle/actions)
[![codecov](https://codecov.io/gh/silpo-tech/MessageBusBundle/graph/badge.svg)](https://codecov.io/gh/silpo-tech/MessageBusBundle)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Development ##

Run tests
```bash
docker compose -p message-bus -f docker-compose.test.yml up -d --build --remove-orphans && \
docker compose -p message-bus -f docker-compose.yml exec -T api /usr/local/bin/composer install --working-dir=/var/www/project -o --no-interaction --ignore-platform-reqs && \
docker compose -p message-bus -f docker-compose.yml exec -T api /usr/local/bin/composer test:run --working-dir=/var/www/project
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