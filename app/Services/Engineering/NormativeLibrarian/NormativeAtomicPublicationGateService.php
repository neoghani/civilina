<?php

namespace App\Services\Engineering\NormativeLibrarian;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class NormativeAtomicPublicationGateService
{
    public const PUBLISHABLE_TYPES = ['REQUIREMENT', 'TOLERANCE'];

    public function query(): Builder
    {
        return DB::table('civilina_normative_atomic_statements as a')
            ->leftJoin(
                'civilina_normative_atomic_source_reviews as sr',
                'sr.deep_document_id',
                '=',
                'a.deep_document_id'
            )
            ->leftJoin('civilina_normative_deep_sections as s', 's.id', '=', 'a.section_id')
            ->leftJoin('civilina_normative_deep_documents as d', 'd.id', '=', 'a.deep_document_id')
            ->leftJoin('civilina_normative_librarian_documents as l', 'l.id', '=', 'a.document_id')
            ->select([
                'a.*',
                'sr.review_status as source_review_status',
                'sr.reviewed_at as source_reviewed_at',
                'sr.detected_authority as source_detected_authority',
                'sr.detected_reason as source_detected_reason',
                'sr.review_note as source_review_note',
                's.heading as section_heading',
                'd.source_kind',
                'l.title as document_title',
                'l.source_path',
                'l.relative_path',
                'l.ics_codes',
            ]);
    }

    public function evaluate(object|array $statement): array
    {
        $row = (object) $statement;

        if (($row->review_status ?? null) !== 'VALIDATED' || empty($row->reviewed_at)) {
            return $this->blocked('BLOCKED_STATEMENT', 'Validation humaine du statement absente.');
        }

        if (($row->source_review_status ?? null) !== 'PRIMARY_VALIDATED'
            || ($row->source_detected_authority ?? null) !== 'PRIMARY_CANDIDATE'
            || empty($row->source_reviewed_at)) {
            return $this->blocked('BLOCKED_SOURCE', 'Source primaire non validee humainement.');
        }

        if (! $this->hasKnownEdition($row->edition_key ?? null)) {
            return $this->blocked('BLOCKED_EDITION', 'Edition normative inconnue ou bloquee.');
        }

        if (! in_array($row->statement_type ?? null, self::PUBLISHABLE_TYPES, true)) {
            return $this->blocked('BLOCKED_TYPE', 'Type non publiable dans le registre V1.');
        }

        if (trim((string) ($row->statement_text ?? '')) === '') {
            return $this->blocked('BLOCKED_TEXT', 'Texte atomique vide.');
        }

        if (! empty($row->revoked_at) || ($row->extraction_state ?? 'ACTIVE') !== 'ACTIVE') {
            return $this->blocked('BLOCKED_REVOKED', 'Statement revoque, remplace ou en nouvelle revue.');
        }

        if (empty($row->document_id)
            || empty($row->deep_document_id)
            || empty($row->section_id)
            || trim((string) ($row->reference_code ?? '')) === ''
            || empty($row->source_hash)
            || empty($row->provenance_hash)) {
            return $this->blocked('BLOCKED_PROVENANCE', 'Provenance document/section incomplete.');
        }

        return ['eligible' => true, 'code' => 'ELIGIBLE', 'reason' => 'Tous les Gates V1 sont satisfaits.'];
    }

    public function hasKnownEdition(?string $edition): bool
    {
        $value = mb_strtoupper(trim((string) $edition));

        if ($value === '') {
            return false;
        }

        foreach (['ANNEE_INCONNUE', 'ANNEE INCONNUE', 'UNKNOWN', 'INCONNUE', 'BLOCKED_EDITION'] as $blocked) {
            if (str_contains($value, $blocked)) {
                return false;
            }
        }

        return true;
    }

    public function makeProvenanceHash(object|array $statement): string
    {
        $row = (object) $statement;

        return hash('sha256', implode('|', [
            'CIVILINA_ATOMIC_PROVENANCE_V1',
            (string) ($row->deep_document_id ?? ''),
            (string) ($row->document_id ?? ''),
            (string) ($row->section_id ?? ''),
            trim((string) ($row->reference_code ?? '')),
            trim((string) ($row->edition_key ?? '')),
            trim((string) ($row->source_hash ?? '')),
            trim((string) ($row->statement_hash ?? '')),
            trim((string) ($row->semantic_version ?? '')),
        ]));
    }

    public function diagnostics(): array
    {
        $rows = $this->query()->where('a.review_status', 'VALIDATED')->get();
        $published = DB::table('civilina_normative_registry_requirements')
            ->whereNotNull('source_atomic_statement_id')
            ->pluck('source_atomic_statement_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true]);

        $stats = [
            'validated_total' => $rows->count(),
            'eligible_to_publish' => 0,
            'blocked_source' => 0,
            'blocked_edition' => 0,
            'blocked_type' => 0,
            'blocked_provenance' => 0,
            'already_published' => 0,
        ];

        foreach ($rows as $row) {
            $gate = $this->evaluate($row);
            if ($gate['eligible']) {
                $stats['eligible_to_publish']++;
            }
            if (($row->source_review_status ?? null) !== 'PRIMARY_VALIDATED'
                || ($row->source_detected_authority ?? null) !== 'PRIMARY_CANDIDATE'
                || empty($row->source_reviewed_at)) {
                $stats['blocked_source']++;
            }
            if (! $this->hasKnownEdition($row->edition_key ?? null)) {
                $stats['blocked_edition']++;
            }
            if (! in_array($row->statement_type, self::PUBLISHABLE_TYPES, true)) {
                $stats['blocked_type']++;
            }
            if (empty($row->document_id) || empty($row->deep_document_id) || empty($row->section_id)
                || empty($row->reference_code) || empty($row->source_hash) || empty($row->provenance_hash)) {
                $stats['blocked_provenance']++;
            }
            if ($published->has((int) $row->id)) {
                $stats['already_published']++;
            }
        }

        return $stats;
    }

    public function refreshEligibilityForDocument(int $deepDocumentId): void
    {
        foreach ($this->query()->where('a.deep_document_id', $deepDocumentId)->get() as $row) {
            $gate = $this->evaluate($row);
            DB::table('civilina_normative_atomic_statements')->where('id', $row->id)->update([
                'publication_eligible' => $gate['eligible'],
                'eligibility_reason' => $gate['reason'],
                'updated_at' => now(),
            ]);
        }
    }

    private function blocked(string $code, string $reason): array
    {
        return ['eligible' => false, 'code' => $code, 'reason' => $reason];
    }
}
