<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\Stub\Processor;

use AutoMapperPlus\AutoMapper;
use Interop\Queue\Context;
use Interop\Queue\Message;
use MapperBundle\Configuration\AutoMapperConfig;
use MapperBundle\Mapper\Mapper;
use MapperBundle\PreLoader\NullPreLoader;
use MessageBusBundle\EnqueueProcessor\AbstractProcessor;
use MessageBusBundle\EnqueueProcessor\ExceptionHandler\ChainExceptionHandler;
use MessageBusBundle\Tests\Stub\Processor\ExceptionHandler\FinishExceptionHandler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

class TestProcessor extends AbstractProcessor
{
    public const QUEUE = 'test.queue';
    public const ROUTE = 'test.route';

    public mixed $processCallback = null;

    public function __construct()
    {
        $autoMapper = new AutoMapper(new AutoMapperConfig());
        $mapper = new Mapper(
            $autoMapper,
            new PropertyInfoExtractor([new ReflectionExtractor()]),
            new NullPreLoader()
        );

        $chainExceptionHandler = new ChainExceptionHandler();
        $chainExceptionHandler->addHandler(new FinishExceptionHandler(), 100);

        $this->setChainExceptionHandler($chainExceptionHandler);
        $this->setEventDispatcher(new EventDispatcher());
        $this->setMapper($mapper);
    }

    public function doProcess($body, Message $message, Context $session): string
    {
        if (is_callable($this->processCallback)) {
            ($this->processCallback)($body);
        }

        // Throw exception to prevent infinite consumption in tests
        throw new \RuntimeException('TestProcessor executed - stopping consumption');
    }

    public function getSubscribedRoutingKeys(): array
    {
        return [
            self::QUEUE => [self::ROUTE],
        ];
    }
}
