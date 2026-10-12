<?php

namespace App\Services\Engineering\NormativeLibrarian;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class NormativeQaRegistryMatchingService
{
    public function __construct(private readonly NormativeQaRegistryMatcherV3 $matcher)
    {
    }

    public function run(?int $limit = 5, bool $pilot = false, ?int $inspectionId = null): array
    {
        $this->guardSchema();
        if ($pilot) {
            $limit = min($limit ?? 5, 5);
        }

        $legacySeen = DB::table('engineering_qa_normative_links')->count();
        $inspectionQuery = DB::table('engineering_qa_inspections')->orderBy('id');
        if ($inspectionId !== null) {
            $inspectionQuery->where('id', $inspectionId);
        }
        if ($limit !== null) {
            $inspectionQuery->limit(max(1, $limit));
        }
        $inspections = $inspectionQuery->get();
        $registry = $this->eligibleRegistry()->get();
        $now = now();

        $runId = DB::table('civilina_qa_normative_match_runs_v3')->insertGetId([
            'status' => 'RUNNING',
            'pilot' => $pilot,
            'requested_limit' => $limit,
            'inspection_count' => $inspections->count(),
            'eligible_registry_count' => $registry->count(),
            'legacy_seen' => $legacySeen,
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $stats = [
            'run_id' => $runId,
            'pilot' => $pilot ? 1 : 0,
            'inspections' => $inspections->count(),
            'eligible_registry' => $registry->count(),
            'MATCH' => 0,
            'PROPOSED' => 0,
            'NO_MATCH' => 0,
            'legacy_seen' => $legacySeen,
            'legacy_preserved' => 0,
        ];
        $reportRows = [];

        foreach ($inspections as $inspection) {
            $result = $this->bestResult($inspection, $registry->all());
            $registryId = in_array($result['result_status'], ['MATCH', 'PROPOSED'], true)
                ? ($result['provenance']['registry_requirement_id'] ?? null)
                : null;

            $matchId = DB::table('civilina_qa_normative_matches_v3')->insertGetId([
                'matching_run_id' => $runId,
                'inspection_id' => $inspection->id,
                'registry_requirement_id' => $registryId,
                'result_status' => $result['result_status'],
                'score' => $result['score'],
                'business_family' => $result['business_family'],
                'justification' => $result['justification'],
                'evidence' => json_encode($result['evidence'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'provenance' => json_encode($result['provenance'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'matcher_version' => NormativeQaRegistryMatcherV3::VERSION,
                'review_status' => $result['review_status'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $stats[$result['result_status']]++;
            $reportRows[] = [
                'match_id' => $matchId,
                'inspection_id' => $inspection->id,
                'registry_requirement_id' => $registryId,
                'result_status' => $result['result_status'],
                'score' => $result['score'],
                'justification' => $result['justification'],
                'review_status' => $result['review_status'],
            ];
        }

        $stats['legacy_preserved'] = DB::table('engineering_qa_normative_links')->count();
        $stats['report_path'] = $this->writeReport($stats, $reportRows);
        DB::table('civilina_qa_normative_match_runs_v3')->where('id', $runId)->update([
            'status' => 'COMPLETED',
            'match_count' => $stats['MATCH'],
            'proposed_count' => $stats['PROPOSED'],
            'no_match_count' => $stats['NO_MATCH'],
            'legacy_preserved' => $stats['legacy_preserved'],
            'report_path' => $stats['report_path'],
            'finished_at' => now(),
            'updated_at' => now(),
        ]);

        return $stats;
    }

    public function eligibleRegistry()
    {
        return DB::table('civilina_normative_registry_requirements as r')
            ->join('civilina_normative_atomic_statements as a', 'a.id', '=', 'r.source_atomic_statement_id')
            ->join('civilina_normative_atomic_source_reviews as sr', 'sr.deep_document_id', '=', 'r.deep_document_id')
            ->leftJoin('civilina_normative_classifications as cs', function (JoinClause $join): void {
                $join->on('cs.target_id', '=', 'r.source_atomic_statement_id')
                    ->where('cs.target_type', '=', 'STATEMENT');
            })
            ->leftJoin('civilina_normative_classifications as cd', function (JoinClause $join): void {
                $join->on('cd.target_id', '=', 'r.deep_document_id')
                    ->where('cd.target_type', '=', 'DOCUMENT');
            })
            ->where('r.publication_state', 'ACTIVE')
            ->whereIn('r.requirement_type', ['REQUIREMENT', 'TOLERANCE'])
            ->whereNotNull('r.human_validated_at')
            ->whereNotNull('r.source_hash')
            ->whereNotNull('r.provenance_hash')
            ->where('a.review_status', 'VALIDATED')
            ->whereNotNull('a.reviewed_at')
            ->whereNull('a.revoked_at')
            ->where('a.extraction_state', 'ACTIVE')
            ->where('a.publication_eligible', true)
            ->where('sr.detected_authority', 'PRIMARY_CANDIDATE')
            ->where('sr.review_status', 'PRIMARY_VALIDATED')
            ->whereNotNull('sr.reviewed_at')
            ->whereNotNull('r.edition_key')
            ->where('r.edition_key', 'not like', '%ANNEE_INCONNUE%')
            ->where('r.edition_key', 'not like', '%UNKNOWN%')
            ->select([
                'r.*',
                DB::raw('COALESCE(cs.family, cd.family) as classification_family'),
                DB::raw('COALESCE(cs.suggested_family, cd.suggested_family) as suggested_family'),
                DB::raw('COALESCE(cs.classification_status, cd.classification_status) as classification_status'),
            ]);
    }

    private function bestResult(object $inspection, array $registry): array
    {
        if ($registry === []) {
            return $this->emptyNoMatch('Aucune exigence ACTIVE et humainement validée dans le registre.');
        }

        $results = array_map(fn (object $row): array => $this->matcher->match($inspection, $row), $registry);
        usort($results, function (array $left, array $right): int {
            $priority = ['MATCH' => 3, 'PROPOSED' => 2, 'NO_MATCH' => 1];
            $status = $priority[$right['result_status']] <=> $priority[$left['result_status']];

            return $status !== 0 ? $status : ($right['score'] <=> $left['score']);
        });

        $best = $results[0];
        if ($best['result_status'] === 'NO_MATCH') {
            $best['provenance']['registry_requirement_id'] = null;
            $best['provenance']['considered_registry_count'] = count($registry);
        }

        return $best;
    }

    private function emptyNoMatch(string $reason): array
    {
        return [
            'result_status' => 'NO_MATCH',
            'score' => 0,
            'business_family' => null,
            'justification' => $reason,
            'evidence' => [],
            'provenance' => [
                'registry_requirement_id' => null,
                'publication_state' => null,
                'matcher_version' => NormativeQaRegistryMatcherV3::VERSION,
                'considered_registry_count' => 0,
            ],
            'review_status' => 'NOT_REQUIRED',
        ];
    }

    private function writeReport(array $stats, array $matches): string
    {
        $directory = storage_path('app/private/engineering/reports/qa-normative-v3');
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.'pilot_'.$stats['run_id'].'_'.now()->format('Ymd_His').'.json';
        File::put($path, json_encode([
            'stats' => $stats,
            'matches' => $matches,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function guardSchema(): void
    {
        foreach ([
            'engineering_qa_inspections',
            'engineering_qa_normative_links',
            'civilina_normative_registry_requirements',
            'civilina_normative_atomic_statements',
            'civilina_normative_atomic_source_reviews',
            'civilina_normative_classifications',
            'civilina_qa_normative_match_runs_v3',
            'civilina_qa_normative_matches_v3',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new \RuntimeException('Table requise absente : '.$table);
            }
        }
    }
}
