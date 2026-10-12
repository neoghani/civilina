<?php

namespace App\Services\Engineering\NormativeLibrarian;

use App\Models\NormativeLibrarianDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class NormativeOcrService
{
    public function __construct(
        private NormativeOcrToolchain $toolchain
    ) {
    }

    /**
     * @return array<string,int|string|null>
     */
    public function run(
        ?int $limit = 20,
        ?int $maxPages = null,
        bool $force = false
    ): array {
        $tools = $this->toolchain->inspect();

        if (! $tools['ready']) {
            throw new \RuntimeException(
                'OCR_TOOLCHAIN_NOT_READY : Tesseract et/ou pdftoppm indisponible.'
            );
        }

        $language = $tools['selected_language'];

        if (! $language) {
            throw new \RuntimeException(
                'OCR_LANGUAGE_NOT_AVAILABLE : aucune langue Tesseract détectée.'
            );
        }

        $maxPages = max(
            1,
            min(10, $maxPages ?: (int) config('civilina_normative_ocr.default_max_pages', 2))
        );

        $dpi = max(120, min(350, (int) config('civilina_normative_ocr.dpi', 220)));

        $query = NormativeLibrarianDocument::query()
            ->where('document_kind', '!=', 'rejected')
            ->where('extension', 'pdf')
            ->whereNull('duplicate_of_id')
            ->where(function ($q) {
                $q->whereNull('version_status')
                    ->orWhere('version_status', '!=', 'SOURCE_COPY')
                    ->orWhereColumn('id', 'canonical_document_id');
            })
            ->where(function ($q) use ($force) {
                $q->where('extraction_status', 'needs_ocr');

                if ($force) {
                    $q->orWhereIn('ocr_status', ['FAILED','LOW_TEXT']);
                }
            })
            ->orderBy('id');

        $eligible = (clone $query)->count();

        if ($limit !== null) {
            $query->limit(max(1, $limit));
        }

        $documents = $query->get();

        $runId = DB::table('civilina_normative_ocr_runs')->insertGetId([
            'status'=>'RUNNING',
            'requested_limit'=>$limit,
            'max_pages'=>$maxPages,
            'dpi'=>$dpi,
            'engine'=>'tesseract+pdftoppm',
            'language'=>$language,
            'eligible'=>$eligible,
            'started_at'=>now(),
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);

        $reportDir = rtrim(
            (string) config(
                'civilina_normative_ocr.report_path',
                'D:\\Civilina_Normes_Classees\\_CIVILINA_OCR\\runs'
            ),
            '\\/'
        ).DIRECTORY_SEPARATOR.'run_'.$runId.'_'.now()->format('Ymd_His');

        File::ensureDirectoryExists($reportDir);

        $csvPath = $reportDir.DIRECTORY_SEPARATOR.'documents.csv';
        $summaryPath = $reportDir.DIRECTORY_SEPARATOR.'summary.json';

        $csv = fopen($csvPath, 'wb');

        if (! $csv) {
            throw new \RuntimeException('Impossible de créer le rapport OCR : '.$csvPath);
        }

        fwrite($csv, "\xEF\xBB\xBF");
        fputcsv($csv, [
            'document_id','reference','filename','status','pages','chars',
            'language','error','source_path'
        ], ';');

        $stats = [
            'run_id'=>$runId,
            'eligible'=>$eligible,
            'selected'=>$documents->count(),
            'processed'=>0,
            'success'=>0,
            'failed'=>0,
            'low_text'=>0,
            'skipped'=>0,
            'pages_ocr'=>0,
            'chars'=>0,
            'language'=>$language,
            'report_path'=>$reportDir,
        ];

        $lastError = null;

        foreach ($documents as $document) {
            $stats['processed']++;

            try {
                $result = $this->ocrDocument(
                    $document,
                    $tools,
                    $language,
                    $maxPages,
                    $dpi
                );

                $stats['pages_ocr'] += $result['pages'];
                $stats['chars'] += $result['chars'];

                if ($result['status'] === 'SUCCESS') {
                    $stats['success']++;
                } elseif ($result['status'] === 'LOW_TEXT') {
                    $stats['low_text']++;
                } else {
                    $stats['failed']++;
                }

                fputcsv($csv, [
                    $document->id,
                    $document->reference_code,
                    $document->filename,
                    $result['status'],
                    $result['pages'],
                    $result['chars'],
                    $language,
                    $result['error'],
                    $document->source_path,
                ], ';');
            } catch (\Throwable $e) {
                report($e);
                $lastError = $e->getMessage();
                $stats['failed']++;

                $document->forceFill([
                    'ocr_status'=>'FAILED',
                    'ocr_engine'=>'tesseract+pdftoppm',
                    'ocr_language'=>$language,
                    'ocr_last_error'=>Str::limit($e->getMessage(), 5000, ''),
                    'ocr_at'=>now(),
                ])->save();

                fputcsv($csv, [
                    $document->id,
                    $document->reference_code,
                    $document->filename,
                    'FAILED',
                    0,
                    0,
                    $language,
                    $e->getMessage(),
                    $document->source_path,
                ], ';');
            }
        }

        fclose($csv);

        File::put(
            $summaryPath,
            json_encode(
                $stats,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        DB::table('civilina_normative_ocr_runs')
            ->where('id', $runId)
            ->update([
                'status'=>$stats['failed'] > 0 ? 'COMPLETED_WITH_ERRORS' : 'COMPLETED',
                'processed'=>$stats['processed'],
                'success'=>$stats['success'],
                'failed'=>$stats['failed'] + $stats['low_text'],
                'skipped'=>$stats['skipped'],
                'report_path'=>$reportDir,
                'last_error'=>$lastError,
                'finished_at'=>now(),
                'updated_at'=>now(),
            ]);

        return $stats;
    }

    /**
     * @return array{status:string,pages:int,chars:int,error:?string}
     */
    private function ocrDocument(
        NormativeLibrarianDocument $document,
        array $tools,
        string $language,
        int $maxPages,
        int $dpi
    ): array {
        $source = (string) $document->source_path;

        if ($source === '' || ! File::exists($source)) {
            throw new \RuntimeException('PDF source introuvable : '.$source);
        }

        $tempRoot = rtrim(
            (string) config(
                'civilina_normative_ocr.temp_path',
                storage_path('app/private/engineering/ocr_tmp')
            ),
            '\\/'
        );

        $workDir = $tempRoot
            .DIRECTORY_SEPARATOR.'doc_'.$document->id
            .'_'.Str::lower(Str::random(8));

        File::ensureDirectoryExists($workDir);

        try {
            $prefix = $workDir.DIRECTORY_SEPARATOR.'page';

            $render = new Process([
                $tools['pdftoppm'],
                '-f', '1',
                '-l', (string) $maxPages,
                '-r', (string) $dpi,
                '-gray',
                '-png',
                $source,
                $prefix,
            ]);

            $render->setTimeout(
                (int) config('civilina_normative_ocr.render_timeout_seconds', 180)
            );
            $render->run();

            if (! $render->isSuccessful()) {
                throw new \RuntimeException(
                    'pdftoppm : '.trim($render->getErrorOutput()."\n".$render->getOutput())
                );
            }

            $images = collect(File::files($workDir))
                ->filter(fn ($file) => strtolower($file->getExtension()) === 'png')
                ->sortBy(function ($file) {
                    if (preg_match('/-(\d+)\.png$/i', $file->getFilename(), $m)) {
                        return (int) $m[1];
                    }

                    return 999999;
                })
                ->values();

            if ($images->isEmpty()) {
                throw new \RuntimeException('pdftoppm n’a généré aucune image.');
            }

            $texts = [];
            $pageNumber = 0;

            foreach ($images as $image) {
                $pageNumber++;

                $ocr = new Process([
                    $tools['tesseract'],
                    $image->getPathname(),
                    'stdout',
                    '-l', $language,
                    '--oem', '1',
                    '--psm', '3',
                    '-c', 'preserve_interword_spaces=1',
                ]);

                $ocr->setTimeout(
                    (int) config('civilina_normative_ocr.ocr_timeout_seconds', 120)
                );
                $ocr->run();

                if (! $ocr->isSuccessful()) {
                    throw new \RuntimeException(
                        'tesseract page '.$pageNumber.' : '
                        .trim($ocr->getErrorOutput()."\n".$ocr->getOutput())
                    );
                }

                $text = $this->cleanText($ocr->getOutput());
                $texts[$pageNumber] = $text;

                DB::table('civilina_normative_ocr_pages')->updateOrInsert(
                    [
                        'document_id'=>$document->id,
                        'page_number'=>$pageNumber,
                    ],
                    [
                        'text'=>$text,
                        'char_count'=>mb_strlen($text),
                        'engine'=>'tesseract+pdftoppm',
                        'language'=>$language,
                        'dpi'=>$dpi,
                        'text_sha256'=>hash('sha256', $text),
                        'updated_at'=>now(),
                        'created_at'=>now(),
                    ]
                );
            }

            $fullText = trim(implode("\n\n--- PAGE ---\n\n", $texts));
            $chars = mb_strlen($fullText);
            $minimumChars = max(
                20,
                (int) config('civilina_normative_ocr.minimum_chars', 120)
            );

            $status = $chars >= $minimumChars ? 'SUCCESS' : 'LOW_TEXT';

            $updates = [
                'ocr_status'=>$status,
                'ocr_engine'=>'tesseract+pdftoppm',
                'ocr_language'=>$language,
                'ocr_pages'=>count($texts),
                'ocr_chars'=>$chars,
                'ocr_text'=>$fullText,
                'ocr_last_error'=>$status === 'LOW_TEXT'
                    ? 'Texte OCR insuffisant : '.$chars.' caractères.'
                    : null,
                'ocr_at'=>now(),
            ];

            if ($status === 'SUCCESS') {
                $updates['extraction_status'] = 'ocr_extracted';

                /*
                 * Le moteur de récupération de référence lit cover_text.
                 * On n'écrase jamais un vrai texte existant.
                 */
                if (
                    Schema::hasColumn('civilina_normative_librarian_documents', 'cover_text')
                    && trim((string) $document->cover_text) === ''
                ) {
                    $updates['cover_text'] = mb_substr($fullText, 0, 24000);
                }
            }

            $document->forceFill($updates)->save();

            return [
                'status'=>$status,
                'pages'=>count($texts),
                'chars'=>$chars,
                'error'=>$status === 'LOW_TEXT'
                    ? 'Texte OCR insuffisant.'
                    : null,
            ];
        } finally {
            try {
                File::deleteDirectory($workDir);
            } catch (\Throwable) {
                // Nettoyage non bloquant.
            }
        }
    }

    private function cleanText(string $text): string
    {
        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace("/\R{3,}/", "\n\n", (string) $text);

        return trim((string) $text);
    }
}
