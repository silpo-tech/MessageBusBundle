<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\Stub\Processor;

use Interop\Queue\Context;
use MessageBusBundle\EnqueueProcessor\Batch\AbstractBatchProcessor;
use MessageBusBundle\EnqueueProcessor\Batch\Result;
use MessageBusBundle\EnqueueProcessor\ExceptionHandler\ChainExceptionHandler;
use MessageBusBundle\Exception\InterruptProcessingException;
use Symfony\Component\EventDispatcher\EventDispatcher;

class TestBatchProcessor extends AbstractBatchProcessor
{
    public const QUEUE = 'test.batch.queue';
    public const ROUTE = 'test.batch.route';

    public Result $result;
    public bool $executedSuccessfully = false;

    public function __construct()
    {
        $this->result = Result::reject(1);
        $this->eventDispatcher = new EventDispatcher();
        $this->chainExceptionHandler = new ChainExceptionHandler();
    }

    public function doProcess(array $messages, Context $session): never
    {
        $this->executedSuccessfully = true;

        $exception = new InterruptProcessingException('TestBatchProcessor executed - stopping consumption');
        $exception->setResult([$this->result]);

        throw $exception;
    }

    public function getSubscribedRoutingKeys(): array
    {
        return [
            self::QUEUE => [self::ROUTE],
        ];
    }
}
