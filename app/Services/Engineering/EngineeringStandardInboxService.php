<?php

namespace App\Services\Engineering;

use App\Models\EngineeringStandardInboxFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class EngineeringStandardInboxService
{
    public function __construct(
        private readonly EngineeringStandardReferenceMatcher $matcher
    ) {
    }

    public function directory(): string
    {
        return storage_path('app/private/engineering/standards_inbox');
    }

    /**
     * Scan incrémental par lots.
     *
     * Corrige le timeout du premier Block 2K.2 :
     * - catalogue StandardDocument chargé une seule fois ;
     * - Inbox chargée une seule fois ;
     * - aucun re-hash des fichiers inchangés ;
     * - un seul UPSERT par lot ;
     * - 10/20/50 fichiers maximum par requête web.
     */
    public function scan(int $batchSize = 20): array
    {
        $batchSize = max(1, min($batchSize, 100));

        $directory = $this->directory();
        File::ensureDirectoryExists($directory);

        $allowed = ['pdf', 'doc', 'docx', 'txt'];

        $files = collect(File::allFiles($directory))
            ->filter(fn ($file): bool => in_array(mb_strtolower($file->getExtension()), $allowed, true))
            ->sortBy(fn ($file): string => str_replace('\\', '/', $file->getRelativePathname()))
            ->values();

        $existing = EngineeringStandardInboxFile::query()
            ->get()
            ->keyBy('relative_path');

        $catalog = $this->standardDocumentCatalog();

        $pending = $files->filter(function ($file) use ($existing): bool {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $record = $existing->get($relative);

            if (! $record) {
                return true;
            }

            $sameSize = (int) $record->size_bytes === (int) $file->getSize();
            $lastScan = $record->last_scanned_at?->getTimestamp() ?? 0;
            $mtime = (int) $file->getMTime();

            return ! ($sameSize && $lastScan >= $mtime);
        })->values();

        $batch = $pending->take($batchSize);
        $rows = [];
        $errors = 0;
        $now = now();

        foreach ($batch as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $extension = mb_strtolower($file->getExtension());
            $filenameBase = $file->getFilenameWithoutExtension();

            try {
                $sha256 = hash_file('sha256', $file->getRealPath());
                if ($sha256 === false) {
                    throw new \RuntimeException('Impossible de calculer le SHA-256.');
                }

                $detectedReference = $this->matcher->detectReference($filenameBase);
                $match = $this->matcher->match($filenameBase, $detectedReference, $catalog);

                $rows[] = [
                    'relative_path' => $relative,
                    'original_name' => $file->getFilename(),
                    'extension' => $extension,
                    'sha256' => $sha256,
                    'size_bytes' => $file->getSize(),
                    'status' => $match
                        ? EngineeringStandardInboxFile::STATUS_MATCHED
                        : EngineeringStandardInboxFile::STATUS_PENDING,
                    'standard_document_id' => $match ? (string) $match['id'] : null,
                    'detected_reference' => $detectedReference,
                    'notes' => $match
                        ? 'Rapprochement local avec la bibliothèque Civilina : '.$match['label'].' (score '.$match['score'].'%).'
                        : null,
                    'last_scanned_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            } catch (\Throwable $e) {
                $errors++;

                $rows[] = [
                    'relative_path' => $relative,
                    'original_name' => $file->getFilename(),
                    'extension' => $extension,
                    'sha256' => str_repeat('0', 64),
                    'size_bytes' => $file->getSize(),
                    'status' => EngineeringStandardInboxFile::STATUS_SCAN_ERROR,
                    'standard_document_id' => null,
                    'detected_reference' => $this->matcher->detectReference($filenameBase),
                    'notes' => 'Erreur scan : '.$e->getMessage(),
                    'last_scanned_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::transaction(function () use ($rows): void {
                EngineeringStandardInboxFile::query()->upsert(
                    $rows,
                    ['relative_path'],
                    [
                        'original_name',
                        'extension',
                        'sha256',
                        'size_bytes',
                        'status',
                        'standard_document_id',
                        'detected_reference',
                        'notes',
                        'last_scanned_at',
                        'updated_at',
                    ]
                );
            });
        }

        $processed = count($rows);
        $remaining = max(0, $pending->count() - $processed);

        return [
            'directory' => $directory,
            'total_files' => $files->count(),
            'already_current' => max(0, $files->count() - $pending->count()),
            'processed' => $processed,
            'remaining' => $remaining,
            'errors' => $errors,
            'complete' => $remaining === 0,
        ];
    }

    private function standardDocumentCatalog(): array
    {
        if (! class_exists(\App\Models\StandardDocument::class)) {
            return [];
        }

        $model = new \App\Models\StandardDocument();
        $table = $model->getTable();

        if (! Schema::hasTable($table)) {
            return [];
        }

        $columns = Schema::getColumnListing($table);
        $searchColumns = array_values(array_intersect(
            ['reference', 'code', 'number', 'document_number', 'title', 'name'],
            $columns
        ));

        if ($searchColumns === []) {
            return [];
        }

        $keyName = $model->getKeyName();
        $select = array_values(array_unique(array_merge([$keyName], $searchColumns)));

        return \App\Models\StandardDocument::query()
            ->select($select)
            ->get()
            ->map(function ($document) use ($keyName, $searchColumns): array {
                $values = [];

                foreach ($searchColumns as $column) {
                    $value = trim((string) ($document->{$column} ?? ''));
                    if ($value !== '') {
                        $values[] = $value;
                    }
                }

                return [
                    'id' => (string) $document->{$keyName},
                    'label' => $values[0] ?? (string) $document->{$keyName},
                    'values' => array_values(array_unique($values)),
                ];
            })
            ->all();
    }
}
