<?php

namespace App\Services\Engineering;

use App\Enums\AnalysisResultStatus;
use App\Enums\AnalysisRunStatus;
use App\Models\AnalysisModelItem;
use App\Models\AnalysisResult;
use App\Models\AnalysisResultSet;
use App\Models\AnalysisRun;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class AnalysisResultImportService
{
    public function markSubmitted(
        AnalysisRun $run,
        string $externalReference
    ): AnalysisRun {
        if ($run->status !== AnalysisRunStatus::Prepared) {
            throw new DomainException(
                'Only a prepared analysis run can be submitted.'
            );
        }

        $run->status = AnalysisRunStatus::Submitted;
        $run->external_reference = trim($externalReference);
        $run->save();

        return $run->refresh();
    }

    public function markRunning(
        AnalysisRun $run
    ): AnalysisRun {
        if ($run->status !== AnalysisRunStatus::Submitted) {
            throw new DomainException(
                'Only a submitted analysis run can start.'
            );
        }

        $run->status = AnalysisRunStatus::Running;
        $run->started_at = now();
        $run->save();

        return $run->refresh();
    }

    public function importSucceededRun(
        AnalysisRun $run,
        array $rows,
        ?string $solverVersion = null,
        ?string $sourceReference = null,
        ?User $importedBy = null,
        array $summary = []
    ): AnalysisResultSet {
        if (
            ! in_array(
                $run->status,
                [
                    AnalysisRunStatus::Prepared,
                    AnalysisRunStatus::Submitted,
                    AnalysisRunStatus::Running,
                ],
                true
            )
        ) {
            throw new DomainException(
                'Analysis results can only be imported into an active analysis run.'
            );
        }

        if ($rows === []) {
            throw new DomainException(
                'Analysis result set cannot be empty.'
            );
        }

        return DB::transaction(function () use (
            $run,
            $rows,
            $solverVersion,
            $sourceReference,
            $importedBy,
            $summary
        ): AnalysisResultSet {
            $normalizedRows = [];

            foreach ($rows as $row) {
                $normalizedRows[] =
                    $this->normalizeResultRow(
                        $run,
                        $row
                    );
            }

            $resultHash = hash(
                'sha256',
                json_encode(
                    $normalizedRows,
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_PRESERVE_ZERO_FRACTION
                )
            );

            $set = AnalysisResultSet::query()->create([
                'analysis_run_id' => $run->getKey(),
                'status' => AnalysisResultStatus::Imported,
                'solver_version' => $this->optionalText($solverVersion),
                'source_reference' => $this->optionalText($sourceReference),
                'result_hash' => $resultHash,
                'summary' => $summary,
                'imported_by_user_id' => $importedBy?->getKey(),
                'imported_at' => now(),
            ]);

            foreach ($normalizedRows as $row) {
                AnalysisResult::query()->create([
                    'analysis_result_set_id' => $set->getKey(),
                    ...$row,
                ]);
            }

            $run->status = AnalysisRunStatus::Succeeded;
            $run->finished_at = now();
            $run->result_summary = [
                ...$summary,
                'result_set_id' => $set->getKey(),
                'result_count' => count($normalizedRows),
                'result_hash' => $resultHash,
            ];
            $run->save();

            return $set->refresh();
        });
    }

    private function normalizeResultRow(
        AnalysisRun $run,
        array $row
    ): array {
        $externalKey =
            trim((string) ($row['external_key'] ?? ''));

        if ($externalKey === '') {
            throw new DomainException(
                'Every solver result must reference a Civilina analysis model item.'
            );
        }

        $item =
            AnalysisModelItem::query()
                ->where(
                    'analysis_model_id',
                    $run->analysis_model_id
                )
                ->where(
                    'external_key',
                    $externalKey
                )
                ->first();

        if (! $item) {
            throw new DomainException(
                'Solver result references an unknown analysis model item.'
            );
        }

        $resultType =
            trim((string) ($row['result_type'] ?? ''));

        if ($resultType === '') {
            throw new DomainException(
                'Solver result type is required.'
            );
        }

        if (! array_key_exists('value', $row)) {
            throw new DomainException(
                'Solver result value is required.'
            );
        }

        return [
            'technical_object_revision_id' =>
                $item->technical_object_revision_id,
            'result_type' => $resultType,
            'component' =>
                $this->optionalText(
                    $row['component'] ?? null
                ),
            'load_case_code' =>
                $this->optionalText(
                    $row['load_case_code'] ?? null
                ),
            'combination_code' =>
                $this->optionalText(
                    $row['combination_code'] ?? null
                ),
            'location' => $row['location'] ?? null,
            'value' => (float) $row['value'],
            'unit' =>
                $this->optionalText(
                    $row['unit'] ?? null
                ),
            'raw_payload' =>
                $row['raw_payload'] ?? null,
        ];
    }

    private function optionalText(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
