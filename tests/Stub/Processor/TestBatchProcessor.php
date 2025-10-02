<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\Stub\Processor;

use Interop\Queue\Context;
use MessageBusBundle\EnqueueProcessor\Batch\AbstractBatchProcessor;
use MessageBusBundle\EnqueueProcessor\ExceptionHandler\ChainExceptionHandler;
use Symfony\Component\EventDispatcher\EventDispatcher;

class TestBatchProcessor extends AbstractBatchProcessor
{
    public const QUEUE = 'test.batch.queue';
    public const ROUTE = 'test.batch.route';

    public function __construct()
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->chainExceptionHandler = new ChainExceptionHandler();
        $this->chainExceptionHandler->addHandler(new FinishExceptionHandler(), 100);
    }

    public function doProcess(array $messages, Context $session): array
    {
        throw new \RuntimeException('Forward to FinishExceptionHandler');
    }

    public function getSubscribedRoutingKeys(): array
    {
        return [
            self::QUEUE => [self::ROUTE],
        ];
    }
}
