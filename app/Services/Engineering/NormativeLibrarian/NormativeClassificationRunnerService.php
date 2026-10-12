<?php

namespace App\Services\Engineering\NormativeLibrarian;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class NormativeClassificationRunnerService
{
    public function __construct(
        private readonly NormativeClassificationPreparationService $classifier
    ) {
    }

    public function run(?int $limit = null, bool $pilot = false): array
    {
        $this->guardSchema();

        if ($pilot) {
            $limit = min($limit ?? 5, 5);
        }

        $documents = DB::table('civilina_normative_deep_documents as d')
            ->leftJoin('civilina_normative_librarian_documents as l', 'l.id', '=', 'd.document_id')
            ->select([
                'd.*',
                'l.title',
                'l.ics_codes',
                'l.source_path',
                'l.relative_path',
                'l.metadata',
                'l.category_label',
                'l.discipline',
                'l.domain',
                'l.subdomain',
                'l.classification_status as legacy_classification_status',
                'l.canonical_group_key',
                'l.canonical_document_id',
                'l.duplicate_of_id',
            ])
            ->orderBy('d.id')
            ->get();

        $groups = $documents->groupBy(fn (object $row): string => $this->canonicalKey($row));
        $representatives = $groups->map(fn ($rows) => $rows->first())->values();
        if ($limit !== null) {
            $representatives = $representatives->take(max(1, $limit));
        }

        $stats = [
            'pilot' => $pilot ? 1 : 0,
            'documents_seen' => $documents->count(),
            'canonical_groups_seen' => $groups->count(),
            'processed' => 0,
            'duplicates_collapsed' => max(0, $documents->count() - $groups->count()),
            'CLASSIFIED' => 0,
            'TO_REVIEW' => 0,
            'UNCLASSIFIED' => 0,
        ];
        $reportRows = [];

        foreach ($representatives as $document) {
            $existing = DB::table('civilina_normative_classifications')
                ->where('target_type', 'DOCUMENT')
                ->where('target_id', $document->id)
                ->first();

            $input = [
                'existing_classification' => $existing?->classification_status === 'CLASSIFIED'
                    ? $existing->family
                    : null,
                'reference' => $document->reference_code,
                'title' => $document->title,
                'ics' => $document->ics_codes,
                'path' => $document->relative_path ?: $document->source_path,
                'metadata' => array_filter([
                    'category' => $document->category_label,
                    'discipline' => $document->discipline,
                    'domain' => $document->domain,
                    'subdomain' => $document->subdomain,
                ]),
                'content' => mb_substr((string) $document->raw_text, 0, 50000),
            ];
            $result = $this->classifier->prepare($input);
            $now = now();

            if (! $existing || $existing->classification_status !== 'CLASSIFIED') {
                DB::table('civilina_normative_classifications')->updateOrInsert(
                    ['target_type' => 'DOCUMENT', 'target_id' => $document->id],
                    [
                        'document_id' => $document->document_id,
                        'deep_document_id' => $document->id,
                        'atomic_statement_id' => null,
                        'family' => $result['family'],
                        'suggested_family' => $result['suggested_family'],
                        'classification_status' => $result['status'],
                        'evidence' => json_encode($result['evidence'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'source_fields' => json_encode([
                            'reference' => $document->reference_code,
                            'edition' => $document->edition_key,
                            'title' => $document->title,
                            'ics' => $document->ics_codes,
                            'path' => $document->relative_path ?: $document->source_path,
                            'content_hash' => hash('sha256', (string) $document->raw_text),
                            'canonical_group' => $this->canonicalKey($document),
                            'copy_count' => $groups->get($this->canonicalKey($document))->count(),
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'updated_at' => $now,
                        'created_at' => $existing?->created_at ?? $now,
                    ]
                );
            }

            $stats['processed']++;
            $stats[$result['status']]++;
            $reportRows[] = [
                'deep_document_id' => $document->id,
                'reference' => $document->reference_code,
                'status' => $result['status'],
                'family' => $result['family'],
                'suggested_family' => $result['suggested_family'],
                'canonical_group' => $this->canonicalKey($document),
                'copy_count' => $groups->get($this->canonicalKey($document))->count(),
                'reason' => $result['reason'],
            ];
        }

        $stats['report_path'] = $this->writeReport($stats, $reportRows, $pilot);

        return $stats;
    }

    private function canonicalKey(object $document): string
    {
        if (trim((string) $document->canonical_group_key) !== '') {
            return 'CANONICAL:'.mb_strtoupper(trim((string) $document->canonical_group_key));
        }

        $reference = mb_strtoupper(trim((string) $document->reference_code));
        $edition = mb_strtoupper(trim((string) $document->edition_key));

        return $reference !== ''
            ? 'REFERENCE:'.$reference.'|'.$edition
            : 'DEEP_DOCUMENT:'.$document->id;
    }

    private function writeReport(array $stats, array $rows, bool $pilot): string
    {
        $directory = storage_path('app/private/engineering/reports/normative-classification');
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR
            .($pilot ? 'pilot_' : 'full_').now()->format('Ymd_His').'.json';

        File::put($path, json_encode([
            'stats' => $stats,
            'classifications' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function guardSchema(): void
    {
        foreach ([
            'civilina_normative_deep_documents',
            'civilina_normative_librarian_documents',
            'civilina_normative_classifications',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new \RuntimeException('Table requise absente : '.$table);
            }
        }
    }
}
