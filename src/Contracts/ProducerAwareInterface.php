<?php

declare(strict_types=1);

namespace MessageBusBundle\Contracts;

use MessageBusBundle\Producer\ProducerInterface;

interface ProducerAwareInterface
{
    public function setProducer(ProducerInterface $producer): void;
}
