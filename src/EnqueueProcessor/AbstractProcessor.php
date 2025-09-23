<?php

declare(strict_types=1);

namespace MessageBusBundle\EnqueueProcessor;

use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\Processor;
use MapperBundle\Mapper\MapperInterface;
use MessageBusBundle\EnqueueProcessor\ExceptionHandler\ChainExceptionHandler;
use MessageBusBundle\Events;
use MessageBusBundle\Events\ConsumeEvent;
use MessageBusBundle\Events\ExceptionConsumeEvent;
use MessageBusBundle\Service\ProducerService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractProcessor implements Processor, ProcessorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected MapperInterface $mapper;

    protected ChainExceptionHandler $chainExceptionHandler;

    protected EventDispatcherInterface $eventDispatcher;

    #[Required]
    public function setMapper(MapperInterface $mapper): self
    {
        $this->mapper = $mapper;

        return $this;
    }

    #[Required]
    public function setChainExceptionHandler(ChainExceptionHandler $chainExceptionHandler): self
    {
        $this->chainExceptionHandler = $chainExceptionHandler;

        return $this;
    }

    #[Required]
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): self
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }

    public function process(Message $message, Context $context): string
    {
        try {
            $event = new Events\PreConsumeEvent($message, $context, static::class);
            $this->eventDispatcher->dispatch($event, Events::CONSUME__PRE_START);

            $body = json_decode($message->getBody(), true);

            if ($destinationClass = $message->getProperty(ProducerService::CLASS_HEADER)) {
                $body = $this->tryConvertBody($body, $destinationClass);
            }

            $event = new ConsumeEvent($body, $message, $context, static::class);
            $this->eventDispatcher->dispatch($event, Events::CONSUME__START);

            $result = $this->doProcess($body, $message, $context);

            $this->eventDispatcher->dispatch($event, Events::CONSUME__FINISHED);

            return $result ?? self::ACK;
        } catch (\Throwable $exception) {
            $event = new ExceptionConsumeEvent($exception, $body, $message, $context, static::class);
            $this->eventDispatcher->dispatch($event, Events::CONSUME__EXCEPTION);

            return $this->chainExceptionHandler->handle($this, $exception, $message, $context);
        }
    }

    /**
     * @return string|void
     */
    abstract public function doProcess($body, Message $message, Context $session);

    protected function tryConvertBody(mixed $source, string $destination): mixed
    {
        return class_exists($destination) ? $this->mapper->convert($source, $destination) : $source;
    }
}
