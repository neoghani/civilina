<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeAtomicSemanticV2Service;
use App\Services\Engineering\NormativeLibrarian\NormativeAtomicSourceAuthorityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CivilinaNormativeAtomicSemanticV2 extends Command
{
    protected $signature = 'civilina:normative-atomic:semantic-v2
        {--limit=10 : Nombre de documents Deep à retraiter}
        {--all : Retraiter tous les documents Deep}';

    protected $description = 'Re-segmente atomiquement avec sémantique V2 et Gate d’autorité de source.';

    public function handle(
        NormativeAtomicSemanticV2Service $semantic,
        NormativeAtomicSourceAuthorityService $authority
    ): int {
        $q = DB::table('civilina_normative_deep_documents')
            ->whereIn('status', ['SUCCESS','PARTIAL'])
            ->whereNotNull('reference_code')
            ->orderBy('id');

        if (! $this->option('all')) {
            $q->limit(max(1, (int) $this->option('limit')));
        }

        $docs = $q->get();

        $stats = [
            'documents'=>$docs->count(),
            'statements'=>0,
            'requirements'=>0,
            'tolerances'=>0,
            'recommendations'=>0,
            'permissions'=>0,
            'procedure_candidates'=>0,
            'limit_candidates'=>0,
            'reference_values'=>0,
            'formula_tables'=>0,
            'other'=>0,
            'blocked_edition_docs'=>0,
            'secondary_docs'=>0,
            'primary_candidate_docs'=>0,
            'unverified_docs'=>0,
        ];

        foreach ($docs as $doc) {
            $source = $authority->detect($doc->edition_key, (string) $doc->raw_text);

            DB::table('civilina_normative_atomic_source_reviews')->updateOrInsert(
                ['deep_document_id'=>$doc->id],
                [
                    'document_id'=>$doc->document_id,
                    'reference_code'=>$doc->reference_code,
                    'edition_key'=>$doc->edition_key,
                    'detected_authority'=>$source['authority'],
                    'detected_reason'=>$source['reason'],
                    'updated_at'=>now(),
                    'created_at'=>now(),
                ]
            );

            match ($source['authority']) {
                'BLOCKED_EDITION' => $stats['blocked_edition_docs']++,
                'SECONDARY_DETECTED' => $stats['secondary_docs']++,
                'PRIMARY_CANDIDATE' => $stats['primary_candidate_docs']++,
                default => $stats['unverified_docs']++,
            };

            $sections = DB::table('civilina_normative_deep_sections')
                ->where('deep_document_id', $doc->id)
                ->orderBy('sequence')
                ->get();

            foreach ($sections as $section) {
                DB::table('civilina_normative_atomic_statements')
                    ->where('section_id', $section->id)
                    ->update([
                        'extraction_state'=>'SUPERSEDED',
                        'superseded_at'=>now(),
                        'publication_eligible'=>false,
                        'eligibility_reason'=>'Absent de la segmentation courante ; conservation pour audit.',
                        'updated_at'=>now(),
                    ]);

                foreach ($semantic->split((string) $section->section_text) as $i=>$text) {
                    $a = $semantic->classify($text);
                    $statementHash = hash('sha256', $text);
                    $sectionHash = hash('sha256', (string) $section->section_text);

                    $eligibilityReason = match ($source['authority']) {
                        'BLOCKED_EDITION' => 'Édition inconnue : publication interdite.',
                        'SECONDARY_DETECTED' => 'Document secondaire détecté : publication normative interdite.',
                        'UNVERIFIED' => 'Autorité de source non vérifiée.',
                        default => 'Source primaire candidate : revue humaine de source requise.',
                    };

                    $payload = [
                        'deep_document_id'=>$doc->id,
                        'document_id'=>$doc->document_id,
                        'section_id'=>$section->id,
                        'reference_code'=>$doc->reference_code,
                        'edition_key'=>$doc->edition_key,
                        'section_code'=>$section->section_code,
                        'sequence'=>$i+1,
                        'statement_type'=>$a['type'],
                        'statement_text'=>$text,
                        'obligation_marker'=>$a['marker'],
                        'has_explicit_limit'=>$a['has_explicit_limit'],
                        'has_numeric_unit'=>$a['has_numeric_unit'],
                        'looks_like_formula'=>$a['looks_like_formula'],
                        'numeric_entities'=>json_encode($a['numeric_entities'], JSON_UNESCAPED_UNICODE),
                        'segmentation_confidence'=>$a['confidence'],
                        'statement_hash'=>$statementHash,
                        'source_hash'=>$sectionHash,
                        'semantic_version'=>NormativeAtomicSemanticV2Service::VERSION,
                        'source_authority'=>$source['authority'],
                        'source_authority_reason'=>$source['reason'],
                        'publication_eligible'=>false,
                        'eligibility_reason'=>$eligibilityReason,
                        'generated_at'=>now(),
                        'extraction_state'=>'ACTIVE',
                        'superseded_at'=>null,
                        'updated_at'=>now(),
                    ];

                    $existing = DB::table('civilina_normative_atomic_statements')
                        ->where('section_id', $section->id)
                        ->where('statement_hash', $statementHash)
                        ->first();

                    if ($existing) {
                        $humanReviewInvalidated = $existing->review_status === 'VALIDATED'
                            && ($existing->statement_type !== $a['type'] || $existing->source_hash !== $sectionHash);

                        if ($humanReviewInvalidated) {
                            $payload['extraction_state'] = 'TO_REVIEW';
                            $payload['eligibility_reason'] = 'Contexte ou type modifie ; nouvelle revue humaine requise.';
                        }

                        DB::table('civilina_normative_atomic_statements')
                            ->where('id', $existing->id)
                            ->update($payload);
                    } else {
                        $payload['review_status'] = 'CANDIDATE';
                        $payload['created_at'] = now();
                        DB::table('civilina_normative_atomic_statements')->insert($payload);
                    }

                    $stats['statements']++;

                    $key = match ($a['type']) {
                        'REQUIREMENT'=>'requirements',
                        'TOLERANCE'=>'tolerances',
                        'RECOMMENDATION'=>'recommendations',
                        'PERMISSION'=>'permissions',
                        'PROCEDURE_CANDIDATE'=>'procedure_candidates',
                        'LIMIT_CANDIDATE'=>'limit_candidates',
                        'REFERENCE_VALUE'=>'reference_values',
                        'FORMULA_TABLE'=>'formula_tables',
                        default=>'other',
                    };

                    $stats[$key]++;
                }
            }
        }

        foreach ($stats as $k=>$v) {
            $this->line(str_pad($k, 24).' : '.$v);
        }

        return self::SUCCESS;
    }
}
