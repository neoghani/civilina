<?php

namespace App\Services\Engineering\NormativeLibrarian;

use App\Models\NormativeLibrarianDocument;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class NormativeLibrarianScanner
{
    public function __construct(
        private NormativeTextExtractor $extractor,
        private NormativeMetadataParser $parser
    ) {}

    public function scan(?string $path = null, ?int $limit = 100, bool $force = false): array
    {
        $root = rtrim($path ?: (string) config('civilina_normative_librarian.library_path'), '\\/');

        if (! is_dir($root)) {
            throw new \RuntimeException("Dossier normatif introuvable : {$root}");
        }

        $allowed = array_map('strtolower', (array) config(
            'civilina_normative_librarian.scan_extensions',
            ['pdf','docx','txt','md']
        ));

        $stats = [
            'seen'=>0,'classified'=>0,'review'=>0,'unclassified'=>0,
            'duplicates'=>0,'needs_ocr'=>0,'errors'=>0,'skipped'=>0,
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        $processed = 0;

        foreach ($iterator as $file) {
            if (! $file->isFile()) continue;

            $extension = strtolower($file->getExtension());
            if (! in_array($extension, $allowed, true)) continue;
            if ($limit !== null && $processed >= $limit) break;

            $stats['seen']++;
            $absolute = $file->getPathname();
            $relative = ltrim(str_replace('\\','/',substr($absolute, strlen($root))), '/');
            $sourceKey = hash('sha256', mb_strtolower($absolute));
            $mtime = $file->getMTime();

            $existing = NormativeLibrarianDocument::query()->where('source_key',$sourceKey)->first();

            if ($existing && ! $force && $existing->source_modified_at && $existing->source_modified_at->timestamp === $mtime) {
                $stats['skipped']++;
                continue;
            }

            $processed++;

            try {
                $sha = @hash_file('sha256', $absolute) ?: null;

                $duplicate = $sha
                    ? NormativeLibrarianDocument::query()
                        ->where('sha256',$sha)
                        ->where('source_key','!=',$sourceKey)
                        ->whereNull('duplicate_of_id')
                        ->first()
                    : null;

                $extraction = $this->extractor->extract(
                    $absolute,
                    $extension,
                    (int) config('civilina_normative_librarian.cover_pages', 4)
                );

                $metadata = $this->parser->parse($file->getFilename(), (string) $extraction['text']);

                $document = NormativeLibrarianDocument::query()->updateOrCreate(
                    ['source_key'=>$sourceKey],
                    array_merge($metadata, [
                        'source_path'=>$absolute,
                        'relative_path'=>$relative,
                        'filename'=>$file->getFilename(),
                        'extension'=>$extension,
                        'size_bytes'=>$file->getSize(),
                        'sha256'=>$sha,
                        'duplicate_of_id'=>$duplicate?->id,
                        'source_collection'=>$this->sourceCollection($relative),
                        'document_kind'=>$this->documentKind($file->getFilename(), $relative),
                        'extraction_status'=>$extraction['status'],
                        'cover_text'=>$extraction['text'],
                        'metadata'=>[
                            'extract_message'=>$extraction['message'],
                            'cover_pages_read'=>$extraction['pages'],
                            'root_path'=>$root,
                        ],
                        'source_modified_at'=>date('Y-m-d H:i:s',$mtime),
                        'last_scanned_at'=>now(),
                    ])
                );

                if ($duplicate) $stats['duplicates']++;
                if ($extraction['status'] === 'needs_ocr') $stats['needs_ocr']++;

                match ($document->classification_status) {
                    'AUTO_CLASSIFIED' => $stats['classified']++,
                    'TO_REVIEW' => $stats['review']++,
                    default => $stats['unclassified']++,
                };
            } catch (\Throwable $e) {
                report($e);
                $stats['errors']++;
            }
        }

        return $stats;
    }

    private function sourceCollection(string $relative): string
    {
        $parts = array_values(array_filter(explode('/',str_replace('\\','/',$relative))));
        return count($parts) > 1 ? $parts[0] : 'Racine / non classé';
    }

    private function documentKind(string $filename, string $relative): string
    {
        $text = mb_strtolower($filename.' '.$relative);

        if (str_contains($text,'curriculum') || str_contains($text,'lettre de motivation') || preg_match('/(^|[\/_\-\s])cv([\/_\-\s]|$)/iu',$text)) return 'rejected';
        if (preg_match('/(^|[\/_\-\s])ft([\/_\-\s]|$)/iu',$text)) return 'technical_sheet';
        if (str_contains($text,'cours')) return 'course';
        if (str_contains($text,'guide') || str_contains($text,'fascicule') || str_contains($text,'cctg') || str_contains($text,'cctp')) return 'referential';

        return 'standard';
    }
}
