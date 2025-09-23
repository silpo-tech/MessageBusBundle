<?php

declare(strict_types=1);

namespace MessageBusBundle\Encoder;

trait EncoderRegistryAwareTrait
{
    protected EncoderRegistry $encoderRegistry;

    /**
     * @return EncoderRegistryAwareTrait
     */
    public function setEncoderRegistry(EncoderRegistry $encoderRegistry): self
    {
        $this->encoderRegistry = $encoderRegistry;

        return $this;
    }
}
