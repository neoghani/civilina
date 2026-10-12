<?php

namespace App\Services\Engineering\NormativeLibrarian;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class NormativeControlledPublicationService
{
    public function __construct(
        private readonly NormativeAtomicPublicationGateService $gate,
        private readonly NormativeAtomicValueMapper $valueMapper
    ) {
    }

    public function publish(
        ?int $limit = 10,
        bool $dryRun = false,
        ?int $statementId = null,
        bool $pilot = false,
        string $actor = 'SYSTEM_CONTROLLED'
    ): array {
        $this->guardSchema();

        $query = $this->gate->query()
            ->where('a.review_status', 'VALIDATED')
            ->orderBy('a.reference_code')
            ->orderBy('a.edition_key')
            ->orderBy('a.section_code')
            ->orderBy('a.sequence')
            ->orderBy('a.id');

        if ($statementId !== null) {
            $query->where('a.id', $statementId);
        }

        $eligibleRows = $query->get()
            ->filter(fn (object $row) => $this->gate->evaluate($row)['eligible'])
            ->values();

        if ($pilot) {
            $limit = min($limit ?? 10, 10);
        }

        $rows = $limit === null ? $eligibleRows : $eligibleRows->take(max(1, $limit))->values();
        $now = now();
        $runId = DB::table('civilina_normative_publication_runs')->insertGetId([
            'status' => $dryRun ? 'DRY_RUN' : 'RUNNING',
            'run_mode' => $pilot ? 'PILOT' : 'CONTROLLED',
            'dry_run' => $dryRun,
            'requested_limit' => $limit,
            'eligible' => $eligibleRows->count(),
            'selected' => $rows->count(),
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $stats = [
            'run_id' => $runId,
            'dry_run' => $dryRun ? 1 : 0,
            'pilot' => $pilot ? 1 : 0,
            'eligible' => $eligibleRows->count(),
            'selected' => $rows->count(),
            'published' => 0,
            'republished' => 0,
            'skipped' => 0,
            'refused' => 0,
            'errors' => 0,
        ];

        $lastError = null;
        foreach ($rows as $row) {
            try {
                $stats[$this->publishOne($row, $runId, $dryRun, $actor)]++;
            } catch (\Throwable $exception) {
                report($exception);
                $stats['errors']++;
                $lastError = $exception->getMessage();
            }
        }

        $reportPath = $this->writeReport($runId, $stats, $rows, $dryRun);
        DB::table('civilina_normative_publication_runs')->where('id', $runId)->update([
            'status' => $dryRun
                ? 'DRY_RUN_COMPLETED'
                : ($stats['errors'] > 0 ? 'COMPLETED_WITH_ERRORS' : 'COMPLETED'),
            'published' => $stats['published'],
            'republished' => $stats['republished'],
            'skipped' => $stats['skipped'],
            'refused' => $stats['refused'],
            'errors' => $stats['errors'],
            'report_path' => $reportPath,
            'last_error' => $lastError,
            'finished_at' => now(),
            'updated_at' => now(),
        ]);

        $stats['report_path'] = $reportPath;

        return $stats;
    }

    public function revoke(int $registryRequirementId, string $reason, string $actor = 'SYSTEM_CONTROLLED'): void
    {
        $this->guardSchema();
        $reason = trim($reason);
        if ($reason === '') {
            throw new \DomainException('Le motif de revocation est obligatoire.');
        }

        $row = DB::table('civilina_normative_registry_requirements')
            ->where('id', $registryRequirementId)
            ->first();
        if (! $row) {
            throw new \RuntimeException('Exigence publiee introuvable.');
        }
        if ($row->publication_state === 'REVOKED') {
            return;
        }

        DB::transaction(function () use ($row, $reason, $actor) {
            $now = now();
            DB::table('civilina_normative_registry_requirements')->where('id', $row->id)->update([
                'publication_state' => 'REVOKED',
                'revoked_at' => $now,
                'revocation_reason' => $reason,
                'updated_at' => $now,
            ]);

            $revoked = DB::table('civilina_normative_registry_requirements')->where('id', $row->id)->first();
            $this->recordEvent($revoked, null, 'REVOKE', $reason, $actor);
            $this->recordRevision($revoked, 'REVOKE', $reason, $actor);
        });
    }

    private function publishOne(
        object $row,
        int $runId,
        bool $dryRun,
        string $actor
    ): string {
        if (! $this->gate->evaluate($row)['eligible']) {
            return 'refused';
        }

        $sourceHash = $this->publicationSourceHash($row);
        $existing = DB::table('civilina_normative_registry_requirements')
            ->where('source_atomic_statement_id', $row->id)
            ->first();

        if ($existing
            && hash_equals((string) $existing->source_hash, $sourceHash)
            && hash_equals((string) $existing->provenance_hash, (string) $row->provenance_hash)
            && $existing->publication_state === 'ACTIVE') {
            return 'skipped';
        }

        if ($dryRun) {
            return $existing ? 'republished' : 'published';
        }

        return DB::transaction(function () use ($row, $runId, $sourceHash, $existing, $actor) {
            $revision = $existing ? ((int) $existing->revision + 1) : 1;
            $payload = $this->payload($row, $runId, $sourceHash, $revision, $actor);

            if ($existing) {
                DB::table('civilina_normative_registry_requirements')
                    ->where('id', $existing->id)
                    ->update($payload);
                $registryId = $existing->id;
                $eventType = 'REPUBLISH';
                $result = 'republished';
            } else {
                $payload['source_candidate_id'] = null;
                $payload['source_atomic_statement_id'] = $row->id;
                $payload['created_at'] = now();
                $registryId = DB::table('civilina_normative_registry_requirements')->insertGetId($payload);
                $eventType = 'PUBLISH';
                $result = 'published';
            }

            $published = DB::table('civilina_normative_registry_requirements')->where('id', $registryId)->first();
            $this->recordEvent(
                $published,
                $runId,
                $eventType,
                'Publication controlee depuis un statement atomique VALIDATED.',
                $actor
            );
            $this->recordRevision($published, $eventType, null, $actor);

            return $result;
        });
    }

    private function payload(
        object $row,
        int $runId,
        string $sourceHash,
        int $revision,
        string $actor
    ): array {
        $numeric = $this->valueMapper->first($row->numeric_entities);

        return [
            'source_document_id' => $row->document_id,
            'deep_document_id' => $row->deep_document_id,
            'source_section_id' => $row->section_id,
            'reference_code' => $row->reference_code,
            'edition_key' => $row->edition_key,
            'section_code' => $row->section_code,
            'heading' => $row->section_heading,
            'requirement_type' => $row->statement_type,
            'requirement_text' => $row->statement_text,
            'marker' => $row->obligation_marker,
            'numeric_value' => $numeric['value'],
            'unit' => $numeric['unit'],
            'operator' => $numeric['operator'],
            'extraction_confidence' => $row->segmentation_confidence,
            'human_validation_note' => $row->review_note,
            'human_validated_at' => $row->reviewed_at,
            'publication_state' => 'ACTIVE',
            'revision' => $revision,
            'source_hash' => $sourceHash,
            'provenance_hash' => $row->provenance_hash,
            'last_publication_run_id' => $runId,
            'publication_actor' => $actor,
            'published_at' => now(),
            'revoked_at' => null,
            'revocation_reason' => null,
            'updated_at' => now(),
        ];
    }

    private function publicationSourceHash(object $row): string
    {
        return hash('sha256', implode('|', [
            trim((string) $row->statement_type),
            trim((string) $row->statement_text),
            trim((string) $row->obligation_marker),
            trim((string) $row->numeric_entities),
            trim((string) $row->source_hash),
        ]));
    }

    private function recordEvent(
        object $row,
        ?int $runId,
        string $type,
        string $reason,
        string $actor
    ): void {
        DB::table('civilina_normative_publication_events')->insert([
            'registry_requirement_id' => $row->id,
            'source_candidate_id' => $row->source_candidate_id,
            'source_atomic_statement_id' => $row->source_atomic_statement_id,
            'publication_run_id' => $runId,
            'event_type' => $type,
            'source_hash' => $row->source_hash,
            'provenance_hash' => $row->provenance_hash,
            'reason' => $reason,
            'actor' => $actor,
            'event_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recordRevision(object $row, string $type, ?string $reason, string $actor): void
    {
        DB::table('civilina_normative_registry_revisions')->insert([
            'registry_requirement_id' => $row->id,
            'source_atomic_statement_id' => $row->source_atomic_statement_id,
            'revision' => $row->revision,
            'event_type' => $type,
            'publication_state' => $row->publication_state,
            'source_hash' => $row->source_hash,
            'provenance_hash' => $row->provenance_hash,
            'snapshot' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'actor' => $actor,
            'reason' => $reason,
            'recorded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function writeReport(int $runId, array $stats, $rows, bool $dryRun): string
    {
        $dir = storage_path('app/private/engineering/reports/normative-publication');
        File::ensureDirectoryExists($dir);
        $path = $dir.DIRECTORY_SEPARATOR.($dryRun ? 'dry_run_' : 'run_')
            .$runId.'_'.now()->format('Ymd_His').'.json';

        File::put($path, json_encode([
            'stats' => $stats,
            'statements' => $rows->map(fn (object $row) => [
                'statement_id' => $row->id,
                'reference_code' => $row->reference_code,
                'edition_key' => $row->edition_key,
                'section_code' => $row->section_code,
                'statement_type' => $row->statement_type,
                'source_decision' => $row->source_review_status,
                'reviewed_at' => $row->reviewed_at,
                'provenance_hash' => $row->provenance_hash,
            ])->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function guardSchema(): void
    {
        foreach ([
            'civilina_normative_atomic_statements',
            'civilina_normative_atomic_source_reviews',
            'civilina_normative_registry_requirements',
            'civilina_normative_registry_revisions',
            'civilina_normative_publication_runs',
            'civilina_normative_publication_events',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new \RuntimeException('Table requise absente : '.$table);
            }
        }
    }
}
