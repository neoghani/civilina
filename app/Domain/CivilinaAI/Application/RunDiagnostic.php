<?php

namespace App\Domain\CivilinaAI\Application;

use App\Domain\CivilinaAI\DTO\DiagnosticInput;
use App\Domain\CivilinaAI\DTO\ReasoningResult;
use App\Domain\CivilinaAI\Reasoning\ReasoningEngine;

final readonly class RunDiagnostic
{
    public function __construct(private ReasoningEngine $engine)
    {
    }

    public function execute(string|array $input): ReasoningResult
    {
        return $this->engine->evaluate(DiagnosticInput::from($input));
    }
}
