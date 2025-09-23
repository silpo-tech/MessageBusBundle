<?php

declare(strict_types=1);

namespace MessageBusBundle\Traits;

use MessageBusBundle\Producer\ProducerInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait ProducerAwareTrait
{
    protected ProducerInterface $producer;

    #[Required]
    public function setProducer(ProducerInterface $producer): void
    {
        $this->producer = $producer;
    }
}
