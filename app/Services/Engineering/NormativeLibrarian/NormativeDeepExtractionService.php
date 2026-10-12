<?php

namespace App\Services\Engineering\NormativeLibrarian;

use App\Models\NormativeLibrarianDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

class NormativeDeepExtractionService
{
    /**
     * Extraction prudente :
     * - texte complet via pdftotext si disponible ;
     * - sinon fallback OCR existant ;
     * - sections candidates ;
     * - exigences/tolérances = CANDIDATES uniquement.
     */
    public function run(?int $limit = 20): array
    {
        $query = NormativeLibrarianDocument::query()
            ->whereNotNull('reference_code')
            ->where(function ($q) {
                $q->whereNull('version_status')
                  ->orWhere('version_status', 'CANONICAL')
                  ->orWhereColumn('id', 'canonical_document_id');
            })
            ->orderBy('id');

        if (Schema::hasColumn('civilina_normative_librarian_documents', 'audit_status')) {
            $query->where(function ($q) {
                $q->where('audit_status', 'SAFE')
                  ->orWhere('classification_status', 'VALIDATED')
                  ->orWhereNull('audit_status');
            });
        }

        if ($limit !== null) {
            $query->limit(max(1, $limit));
        }

        $documents = $query->get();

        $runId = DB::table('civilina_normative_deep_runs')->insertGetId([
            'status'=>'RUNNING',
            'selected'=>$documents->count(),
            'started_at'=>now(),
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);

        $stats = [
            'run_id'=>$runId,
            'selected'=>$documents->count(),
            'processed'=>0,
            'success'=>0,
            'partial'=>0,
            'failed'=>0,
            'sections'=>0,
            'requirements'=>0,
            'tolerances'=>0,
        ];

        foreach ($documents as $document) {
            $stats['processed']++;

            try {
                $result = $this->processDocument($document);

                $stats[$result['status'] === 'SUCCESS' ? 'success' : 'partial']++;
                $stats['sections'] += $result['sections'];
                $stats['requirements'] += $result['requirements'];
                $stats['tolerances'] += $result['tolerances'];
            } catch (\Throwable $e) {
                report($e);
                $stats['failed']++;

                DB::table('civilina_normative_deep_documents')->updateOrInsert(
                    ['document_id'=>$document->id],
                    [
                        'reference_code'=>$document->reference_code,
                        'edition_key'=>$document->edition_key,
                        'status'=>'FAILED',
                        'last_error'=>$e->getMessage(),
                        'processed_at'=>now(),
                        'updated_at'=>now(),
                        'created_at'=>now(),
                    ]
                );
            }
        }

        DB::table('civilina_normative_deep_runs')
            ->where('id', $runId)
            ->update([
                'status'=>$stats['failed'] ? 'COMPLETED_WITH_ERRORS' : 'COMPLETED',
                'processed'=>$stats['processed'],
                'success'=>$stats['success'],
                'partial'=>$stats['partial'],
                'failed'=>$stats['failed'],
                'sections'=>$stats['sections'],
                'requirements'=>$stats['requirements'],
                'tolerances'=>$stats['tolerances'],
                'finished_at'=>now(),
                'updated_at'=>now(),
            ]);

        return $stats;
    }

    private function processDocument(NormativeLibrarianDocument $document): array
    {
        [$text, $sourceKind, $status] = $this->acquireText($document);

        $text = $this->cleanText($text);

        if ($text === '') {
            throw new \RuntimeException('Aucun texte exploitable.');
        }

        $maxChars = max(50000, (int) config('civilina_normative_deep.max_chars', 2500000));

        if (mb_strlen($text) > $maxChars) {
            $text = mb_substr($text, 0, $maxChars);
            $status = 'PARTIAL';
        }

        $deepDocumentId = DB::table('civilina_normative_deep_documents')->updateOrInsert(
            ['document_id'=>$document->id],
            [
                'reference_code'=>$document->reference_code,
                'edition_key'=>$document->edition_key,
                'status'=>$status,
                'source_kind'=>$sourceKind,
                'char_count'=>mb_strlen($text),
                'raw_text'=>$text,
                'last_error'=>null,
                'processed_at'=>now(),
                'updated_at'=>now(),
                'created_at'=>now(),
            ]
        );

        $deepDocument = DB::table('civilina_normative_deep_documents')
            ->where('document_id', $document->id)
            ->first();

        DB::table('civilina_normative_deep_sections')
            ->where('deep_document_id', $deepDocument->id)
            ->delete();

        DB::table('civilina_normative_deep_requirement_candidates')
            ->where('deep_document_id', $deepDocument->id)
            ->delete();

        $sections = $this->extractSections($text);

        $sectionCount = 0;
        $requirementCount = 0;
        $toleranceCount = 0;

        foreach ($sections as $sequence=>$section) {
            $sectionId = DB::table('civilina_normative_deep_sections')->insertGetId([
                'deep_document_id'=>$deepDocument->id,
                'document_id'=>$document->id,
                'section_code'=>$section['code'],
                'heading'=>$section['heading'],
                'level'=>$section['level'],
                'sequence'=>$sequence + 1,
                'section_text'=>$section['text'],
                'text_sha256'=>hash('sha256', $section['text']),
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);

            $sectionCount++;

            foreach ($this->extractCandidates($section['text']) as $candidate) {
                DB::table('civilina_normative_deep_requirement_candidates')->insert([
                    'deep_document_id'=>$deepDocument->id,
                    'section_id'=>$sectionId,
                    'document_id'=>$document->id,
                    'candidate_type'=>$candidate['type'],
                    'status'=>'CANDIDATE',
                    'text'=>$candidate['text'],
                    'marker'=>$candidate['marker'],
                    'numeric_value'=>$candidate['numeric_value'],
                    'unit'=>$candidate['unit'],
                    'operator'=>$candidate['operator'],
                    'confidence'=>$candidate['confidence'],
                    'text_sha256'=>hash('sha256', $candidate['text']),
                    'created_at'=>now(),
                    'updated_at'=>now(),
                ]);

                if ($candidate['type'] === 'TOLERANCE') {
                    $toleranceCount++;
                } else {
                    $requirementCount++;
                }
            }
        }

        DB::table('civilina_normative_deep_documents')
            ->where('id', $deepDocument->id)
            ->update([
                'section_count'=>$sectionCount,
                'requirement_candidate_count'=>$requirementCount,
                'tolerance_candidate_count'=>$toleranceCount,
                'updated_at'=>now(),
            ]);

        return [
            'status'=>$status,
            'sections'=>$sectionCount,
            'requirements'=>$requirementCount,
            'tolerances'=>$toleranceCount,
        ];
    }

    private function acquireText(NormativeLibrarianDocument $document): array
    {
        $source = (string) $document->source_path;
        $pdftotext = (string) config('civilina_normative_deep.pdftotext', 'pdftotext');

        if ($source !== '' && File::exists($source)) {
            try {
                $process = new Process([
                    $pdftotext,
                    '-layout',
                    '-enc', 'UTF-8',
                    $source,
                    '-',
                ]);
                $process->setTimeout(180);
                $process->run();

                $text = trim($process->getOutput());

                if ($process->isSuccessful() && mb_strlen($text) >= 500) {
                    return [$text, 'PDFTOTEXT_FULL', 'SUCCESS'];
                }
            } catch (\Throwable) {
                // fallback ci-dessous.
            }
        }

        foreach (['extracted_text','ocr_text','cover_text'] as $field) {
            if (
                Schema::hasColumn('civilina_normative_librarian_documents', $field)
                && trim((string) $document->{$field}) !== ''
            ) {
                return [
                    (string) $document->{$field},
                    strtoupper($field),
                    $field === 'ocr_text' ? 'PARTIAL' : 'SUCCESS',
                ];
            }
        }

        throw new \RuntimeException('Aucune source texte disponible.');
    }

    private function extractSections(string $text): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];

        $sections = [];
        $current = [
            'code'=>'0',
            'heading'=>'Préambule / texte initial',
            'level'=>1,
            'text'=>'',
        ];

        foreach ($lines as $line) {
            $trim = trim($line);

            if ($trim === '') {
                $current['text'] .= "\n";
                continue;
            }

            if (preg_match('/^((?:\d+\.)*\d+)\s+(.{3,180})$/u', $trim, $m)) {
                if (trim($current['text']) !== '') {
                    $current['text'] = trim($current['text']);
                    $sections[] = $current;
                }

                $code = rtrim($m[1], '.');

                $current = [
                    'code'=>$code,
                    'heading'=>trim($m[2]),
                    'level'=>substr_count($code, '.') + 1,
                    'text'=>$trim."\n",
                ];

                continue;
            }

            $current['text'] .= $trim."\n";
        }

        if (trim($current['text']) !== '') {
            $current['text'] = trim($current['text']);
            $sections[] = $current;
        }

        if (count($sections) === 0) {
            $sections[] = [
                'code'=>'0',
                'heading'=>'Document',
                'level'=>1,
                'text'=>$text,
            ];
        }

        return $sections;
    }

    private function extractCandidates(string $sectionText): array
    {
        $sentences = preg_split('/(?<=[\.\!\?\;])\s+/u', $sectionText) ?: [];
        $markers = (array) config('civilina_normative_deep.requirement_markers', []);
        $units = (array) config('civilina_normative_deep.unit_patterns', []);

        $results = [];
        $seen = [];

        foreach ($sentences as $sentence) {
            $sentence = trim(preg_replace('/\s+/u', ' ', $sentence));

            if (mb_strlen($sentence) < 20 || mb_strlen($sentence) > 1500) {
                continue;
            }

            $lower = mb_strtolower($sentence);
            $matchedMarker = null;

            foreach ($markers as $marker) {
                if (str_contains($lower, mb_strtolower($marker))) {
                    $matchedMarker = $marker;
                    break;
                }
            }

            $numeric = $this->extractNumeric($sentence, $units);

            if (! $matchedMarker && ! $numeric) {
                continue;
            }

            $explicitLimit = preg_match('/(?:Â±|â‰¤|â‰¥|<|>|minimum|maximum|min\.?|max\.?|au moins|au plus|ne doit pas dÃ©passer|infÃ©rieur|supÃ©rieur|tolÃ©rance|Ã©cart admissible|not exceed|at least|at most)/ui', $sentence) === 1;
            if ($numeric && $explicitLimit) {
                $type = 'TOLERANCE';
            } elseif ($matchedMarker) {
                $type = 'REQUIREMENT';
            } elseif ($numeric) {
                $type = 'REFERENCE_VALUE';
            } else {
                $type = 'OTHER';
            }
            $confidence = 45.0;

            if ($matchedMarker) {
                $confidence += 25.0;
            }

            if ($numeric) {
                $confidence += 20.0;
            }

            if (preg_match('/\b(min(?:imum)?|max(?:imum)?|au moins|au plus|inférieur|supérieur|≤|≥|±)\b/ui', $sentence)) {
                $confidence += 10.0;
            }

            $hash = hash('sha256', $sentence);

            if (isset($seen[$hash])) {
                continue;
            }

            $seen[$hash] = true;

            $results[] = [
                'type'=>$type,
                'text'=>$sentence,
                'marker'=>$matchedMarker,
                'numeric_value'=>$numeric['value'] ?? null,
                'unit'=>$numeric['unit'] ?? null,
                'operator'=>$numeric['operator'] ?? null,
                'confidence'=>min(100.0, $confidence),
            ];
        }

        return $results;
    }

    private function extractNumeric(string $sentence, array $units): ?array
    {
        $unitPattern = implode('|', array_map(
            fn ($u) => preg_quote($u, '/'),
            $units
        ));

        $pattern = '/(?P<op>±|≤|≥|<|>|max(?:imum)?|min(?:imum)?|au moins|au plus)?\s*'
            .'(?P<value>\d+(?:[.,]\d+)?)\s*'
            .'(?P<unit>'.$unitPattern.')\b/ui';

        if (! preg_match($pattern, $sentence, $m)) {
            return null;
        }

        return [
            'operator'=>trim((string) ($m['op'] ?? '')) ?: null,
            'value'=>str_replace(',', '.', (string) $m['value']),
            'unit'=>(string) $m['unit'],
        ];
    }

    private function cleanText(string $text): string
    {
        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace("/\R{3,}/u", "\n\n", (string) $text);

        return trim((string) $text);
    }
}
