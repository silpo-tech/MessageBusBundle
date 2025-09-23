<?php

declare(strict_types=1);

namespace MessageBusBundle\Exception;

use Exception;

class InterruptProcessingException extends Exception
{
    private array $result;

    public function getResult(): array
    {
        return $this->result;
    }

    public function setResult(array $result): self
    {
        $this->result = $result;

        return $this;
    }
}
