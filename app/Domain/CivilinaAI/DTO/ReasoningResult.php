<?php

namespace App\Domain\CivilinaAI\DTO;

use JsonSerializable;

final readonly class ReasoningResult implements JsonSerializable
{
    public function __construct(public array $data)
    {
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
