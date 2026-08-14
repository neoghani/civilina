<?php

namespace App\Domain\CivilinaAI\DTO;

final readonly class DiagnosticInput
{
    public function __construct(
        public string $freeText = '',
        public array $answers = [],
    ) {
    }

    public static function from(string|array $input): self
    {
        if (is_string($input)) {
            return new self($input);
        }

        return new self(
            freeText: (string) ($input['freeText'] ?? ''),
            answers: is_array($input['answers'] ?? null) ? $input['answers'] : [],
        );
    }
}
