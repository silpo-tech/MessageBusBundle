<?php

declare(strict_types=1);

namespace MessageBusBundle\AmqpTools;

enum QueueType: string
{
    case DEFAULT = 'default';
    case QUORUM = 'quorum';
}
