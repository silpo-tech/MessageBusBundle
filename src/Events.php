<?php

declare(strict_types=1);

namespace MessageBusBundle;

final class Events
{
    public const CONSUME__PRE_START = 'consume.pre_start';
    public const CONSUME__START = 'consume.start';
    public const CONSUME__FINISHED = 'consume.finished';
    public const CONSUME__EXCEPTION = 'consume.exception';

    public const BATCH_CONSUME__START = 'batch.consume.start';
    public const BATCH_CONSUME__FINISHED = 'batch.consume.finished';
    public const BATCH_CONSUME__EXCEPTION = 'batch.consume.exception';

    public const PRODUCER__PRE_PUBLISH = 'producer.pre_publish';
}
