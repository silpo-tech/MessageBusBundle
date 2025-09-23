<?php

declare(strict_types=1);

namespace MessageBusBundle\Driver;

use Enqueue\Client\Driver\AmqpDriver as BaseAmqpDriver;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Queue\Destination;
use Psr\Log\LoggerInterface;

class AmqpDriver extends BaseAmqpDriver
{
    public function setupBroker(?LoggerInterface $logger = null): void
    {
        parent::setupBroker($logger);

        $routerTopic = $this->createRouterTopic();
        $routerQueue = $this->createQueue($this->getConfig()->getRouterQueue());
        foreach ($this->getRouteCollection()->all() as $route) {
            $this->getContext()->bind(new AmqpBind($routerTopic, $routerQueue, $route->getSource()));
        }
    }

    protected function createRouterTopic(): Destination
    {
        $topic = parent::createRouterTopic();
        $topic->setType(AmqpTopic::TYPE_DIRECT);

        return $topic;
    }
}
