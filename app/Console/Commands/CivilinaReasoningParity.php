<?php

namespace App\Console\Commands;

use App\Domain\CivilinaAI\Application\RunDiagnostic;
use Illuminate\Console\Command;

class CivilinaReasoningParity extends Command
{
    protected $signature = 'civilina:reasoning:parity';
    protected $description = 'Compare the PHP Reasoning Core with the JavaScript Golden Dataset V2';

    public function handle(RunDiagnostic $diagnostic): int
    {
        $dataset = json_decode(file_get_contents(base_path('tests/Fixtures/golden-reasoning-v2.json')), true, 512, JSON_THROW_ON_ERROR);
        $failures = [];

        foreach ($dataset['cases'] as $case) {
            $actual = $diagnostic->execute($case['input'])->jsonSerialize();
            if ($actual !== $case['expected']) {
                $failures[] = [
                    'id' => $case['id'],
                    'differences' => $this->differences($case['expected'], $actual),
                ];
            }
        }

        $total = count($dataset['cases']);
        $report = [
            'suite' => 'CIVILINA Laravel PHP / JavaScript Golden V2 parity',
            'generatedAt' => now()->toIso8601String(),
            'contractVersion' => $dataset['version'],
            'total' => $total,
            'passed' => $total - count($failures),
            'failed' => count($failures),
            'parityPercent' => round(($total - count($failures)) / max(1, $total) * 100, 2),
            'failures' => array_slice($failures, 0, 50),
            'passedGate' => $failures === [] && $total >= 150,
        ];
        file_put_contents(base_path('qa/laravel-parity-validation.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);

        $this->table(['Contract', 'Passed', 'Failed', 'Parity'], [[
            $dataset['version'], $report['passed'], $report['failed'], $report['parityPercent'].'%',
        ]]);

        return $report['passedGate'] ? self::SUCCESS : self::FAILURE;
    }

    private function differences(mixed $expected, mixed $actual, string $path = '$'): array
    {
        if ($expected === $actual) {
            return [];
        }
        if (! is_array($expected) || ! is_array($actual)) {
            return [['path' => $path, 'expected' => $expected, 'actual' => $actual]];
        }

        $differences = [];
        foreach (array_unique(array_merge(array_keys($expected), array_keys($actual))) as $key) {
            if (! array_key_exists($key, $expected) || ! array_key_exists($key, $actual)) {
                $differences[] = ['path' => $path.'.'.$key, 'expected' => $expected[$key] ?? '[missing]', 'actual' => $actual[$key] ?? '[missing]'];
                continue;
            }
            array_push($differences, ...$this->differences($expected[$key], $actual[$key], $path.'.'.$key));
            if (count($differences) >= 20) {
                break;
            }
        }

        return array_slice($differences, 0, 20);
    }
}
