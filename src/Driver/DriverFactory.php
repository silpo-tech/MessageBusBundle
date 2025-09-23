<?php

declare(strict_types=1);

namespace MessageBusBundle\Driver;

use Enqueue\Client\Config;
use Enqueue\Client\DriverFactory as BaseDriverFactory;
use Enqueue\Client\DriverFactoryInterface;
use Enqueue\Client\DriverInterface;
use Enqueue\Client\RouteCollection;
use Interop\Queue\ConnectionFactory;

class DriverFactory implements DriverFactoryInterface
{
    public function create(ConnectionFactory $factory, Config $config, RouteCollection $collection): DriverInterface
    {
        if (0 === strpos($config->getTransportOption('dsn'), 'amqp')) {
            return new AmqpDriver($factory->createContext(), $config, $collection);
        } else {
            return (new BaseDriverFactory())->create($factory, $config, $collection);
        }
    }
}
