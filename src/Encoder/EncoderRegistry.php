<?php

declare(strict_types=1);

namespace MessageBusBundle\Encoder;

class EncoderRegistry
{
    private array $registry;

    public function addEncoder(EncoderInterface $encoder): void
    {
        $this->registry[$encoder->getEncoding()] = $encoder;
    }

    public function getEncoder(string $encoding): EncoderInterface
    {
        $encoder = $this->registry[$encoding] ?? false;
        if (!$encoder instanceof EncoderInterface) {
            throw new \InvalidArgumentException('Invalid encoding');
        }

        return $encoder;
    }
}
