<?php

namespace App\Services\Engineering\NormativeLibrarian;

use App\Models\NormativeLibrarianDocument;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class NormativePhysicalLibraryOrganizer
{
    public const MARKER = '.civilina_normative_classified_root';

    /**
     * Construit le dossier physique.
     *
     * Correctif 2K.3.6.1 :
     * - les CSV/JSON ne sont plus écrits directement à la racine ;
     * - chaque exécution écrit ses index dans _CIVILINA_INDEX/run_YYYYMMDD_HHMMSS ;
     * - un ancien duplicates.csv ouvert dans Excel/aperçu Windows ne bloque plus la reconstruction ;
     * - --clean ne tente plus de supprimer les anciens fichiers de contrôle racine potentiellement verrouillés.
     */
    public function build(
        ?string $destination = null,
        ?string $mode = null,
        bool $clean = false,
        ?int $limit = null
    ): array {
        $destination = rtrim(
            $destination ?: (string) config(
                'civilina_normative_physical_library.classified_path',
                'D:\\Civilina_Normes_Classees'
            ),
            '\\/'
        );

        $mode = strtolower($mode ?: (string) config(
            'civilina_normative_physical_library.mode',
            'hardlink'
        ));

        if (! in_array($mode, ['hardlink', 'copy'], true)) {
            throw new \InvalidArgumentException('Mode attendu : hardlink ou copy.');
        }

        $this->prepareRoot($destination, $clean);

        $runId = 'run_'.now()->format('Ymd_His');
        $indexRoot = $destination.DIRECTORY_SEPARATOR.'_CIVILINA_INDEX';
        $runDir = $indexRoot.DIRECTORY_SEPARATOR.$runId;

        File::ensureDirectoryExists($runDir);

        $manifestPath = $runDir.DIRECTORY_SEPARATOR.'manifest.csv';
        $copiesPath = $runDir.DIRECTORY_SEPARATOR.'source_copies.csv';
        $duplicatesPath = $runDir.DIRECTORY_SEPARATOR.'duplicates.csv';
        $summaryPath = $runDir.DIRECTORY_SEPARATOR.'summary.json';
        $errorsPath = $runDir.DIRECTORY_SEPARATOR.'errors.csv';

        $manifest = $this->openCsv($manifestPath);
        $copies = $this->openCsv($copiesPath);
        $duplicates = $this->openCsv($duplicatesPath);
        $errors = $this->openCsv($errorsPath);

        fputcsv($manifest, [
            'id','reference','year','discipline','domain','subdomain',
            'classification_status','audit_status','version_status',
            'original_path','organized_path','sha256'
        ], ';');

        fputcsv($copies, [
            'id','canonical_document_id','reference','year','source_path','sha256'
        ], ';');

        fputcsv($duplicates, [
            'id','duplicate_of_id','reference','source_path','sha256'
        ], ';');

        fputcsv($errors, [
            'id','reference','source_path','message'
        ], ';');

        $stats = [
            'destination'=>$destination,
            'index_run'=>$runDir,
            'seen'=>0,
            'classified'=>0,
            'auto_to_audit'=>0,
            'to_review'=>0,
            'ocr_required'=>0,
            'unclassified'=>0,
            'exact_duplicates_skipped'=>0,
            'source_copies_skipped'=>0,
            'hardlinks'=>0,
            'copies'=>0,
            'existing'=>0,
            'errors'=>0,
        ];

        $query = NormativeLibrarianDocument::query()->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        foreach ($query->get() as $document) {
            $stats['seen']++;

            if ($document->document_kind === 'rejected') {
                continue;
            }

            if ($document->duplicate_of_id) {
                $stats['exact_duplicates_skipped']++;

                fputcsv($duplicates, [
                    $document->id,
                    $document->duplicate_of_id,
                    $document->reference_code,
                    $document->source_path,
                    $document->sha256,
                ], ';');

                continue;
            }

            if (
                $document->version_status === 'SOURCE_COPY'
                && $document->canonical_document_id
                && (int) $document->canonical_document_id !== (int) $document->id
            ) {
                $stats['source_copies_skipped']++;

                fputcsv($copies, [
                    $document->id,
                    $document->canonical_document_id,
                    $document->reference_code,
                    $document->publication_year,
                    $document->source_path,
                    $document->sha256,
                ], ';');

                continue;
            }

            [$bucket, $key] = $this->bucket($document);
            $stats[$key]++;

            $relative = $this->relativeDestination($document, $bucket);
            $target = $destination.DIRECTORY_SEPARATOR.$relative;

            try {
                $actualMode = $this->materialize(
                    (string) $document->source_path,
                    $target,
                    $mode,
                    $stats
                );

                $document->forceFill([
                    'organized_bucket'=>$bucket,
                    'organized_path'=>$target,
                    'organized_mode'=>$actualMode,
                    'organized_at'=>now(),
                ])->save();

                fputcsv($manifest, [
                    $document->id,
                    $document->reference_code,
                    $document->publication_year,
                    $document->discipline,
                    $document->domain,
                    $document->subdomain,
                    $document->classification_status,
                    $document->audit_status,
                    $document->version_status,
                    $document->source_path,
                    $target,
                    $document->sha256,
                ], ';');
            } catch (\Throwable $e) {
                report($e);
                $stats['errors']++;

                fputcsv($errors, [
                    $document->id,
                    $document->reference_code,
                    $document->source_path,
                    $e->getMessage(),
                ], ';');
            }
        }

        fclose($manifest);
        fclose($copies);
        fclose($duplicates);
        fclose($errors);

        File::put(
            $summaryPath,
            json_encode(
                $stats,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        $this->writeReadme($destination, $runDir);
        $this->writeLatestPointer($indexRoot, $runId, $runDir);

        return $stats;
    }

    private function openCsv(string $path)
    {
        File::ensureDirectoryExists(dirname($path));

        $handle = @fopen($path, 'wb');

        if ($handle === false) {
            throw new \RuntimeException(
                'Impossible de créer le fichier index : '.$path
            );
        }

        // BOM UTF-8 pour Excel Windows.
        fwrite($handle, "\xEF\xBB\xBF");

        return $handle;
    }

    private function bucket(NormativeLibrarianDocument $document): array
    {
        if (
            $document->classification_status === 'VALIDATED'
            || (
                $document->classification_status === 'AUTO_CLASSIFIED'
                && $document->audit_status === 'SAFE'
            )
        ) {
            return ['01_NORMES_CLASSEES', 'classified'];
        }

        if ($document->extraction_status === 'needs_ocr') {
            return ['91_OCR_REQUIS', 'ocr_required'];
        }

        if ($document->classification_status === 'AUTO_CLASSIFIED') {
            return ['02_AUTO_A_AUDITER', 'auto_to_audit'];
        }

        if ($document->classification_status === 'TO_REVIEW') {
            return ['90_A_VALIDER', 'to_review'];
        }

        return ['92_NON_CLASSES', 'unclassified'];
    }

    private function relativeDestination(
        NormativeLibrarianDocument $document,
        string $bucket
    ): string {
        $segments = [$bucket];

        if (in_array($bucket, ['01_NORMES_CLASSEES', '02_AUTO_A_AUDITER'], true)) {
            $segments[] = $this->folder(
                $document->discipline ?: 'Discipline_non_identifiee'
            );
            $segments[] = $this->folder(
                $document->domain ?: 'Domaine_non_identifie'
            );
            $segments[] = $this->folder(
                $document->subdomain ?: 'Sous_domaine_non_identifie'
            );

            $reference = $document->reference_code ?: 'Reference_non_identifiee';
            $segments[] = $this->folder($reference);

            $edition = $document->publication_year
                ? 'Edition_'.$document->publication_year
                : 'Edition_annee_inconnue';

            $segments[] = $this->folder($edition);
        } elseif ($bucket === '90_A_VALIDER') {
            $segments[] = $this->folder(
                $document->discipline ?: 'Discipline_a_confirmer'
            );
            $segments[] = $this->folder(
                $document->domain ?: 'Domaine_a_confirmer'
            );
        } else {
            $segments[] = $this->folder(
                $document->source_collection ?: 'Collection_inconnue'
            );
        }

        $segments[] = $this->safeFilename($document);

        return implode(DIRECTORY_SEPARATOR, $segments);
    }

    private function safeFilename(NormativeLibrarianDocument $document): string
    {
        $extension = strtolower((string) $document->extension);
        $extension = preg_replace('/[^a-z0-9]/i', '', $extension) ?: 'pdf';

        $parts = array_filter([
            $document->reference_code ?: null,
            $document->publication_year ?: null,
            $document->title
                ?: pathinfo((string) $document->filename, PATHINFO_FILENAME),
        ]);

        $base = implode(' - ', array_map(fn ($v) => (string) $v, $parts));
        $base = Str::ascii($base);
        $base = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]/', ' ', $base);
        $base = preg_replace('/\s+/', ' ', trim((string) $base));
        $base = rtrim($base, '. ');
        $base = mb_substr($base, 0, 118);

        if ($base === '') {
            $base = 'document_'.$document->id;
        }

        $suffix = substr((string) $document->sha256, 0, 8);

        return $base.'__'.$suffix.'.'.$extension;
    }

    private function folder(string $value): string
    {
        $value = Str::ascii($value);
        $value = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]/', ' ', $value);
        $value = preg_replace('/\s+/', '_', trim((string) $value));
        $value = preg_replace('/_+/', '_', (string) $value);
        $value = trim((string) $value, '._ ');

        return mb_substr(
            $value !== '' ? $value : 'Non_classe',
            0,
            48
        );
    }

    /**
     * --clean ne nettoie que les arborescences métier.
     * Les anciens CSV/JSON racine peuvent rester ouverts dans Excel sans
     * bloquer la reconstruction.
     */
    private function prepareRoot(string $destination, bool $clean): void
    {
        File::ensureDirectoryExists($destination);

        $marker = $destination.DIRECTORY_SEPARATOR.self::MARKER;

        if (! File::exists($marker)) {
            File::put(
                $marker,
                "CIVILINA NORMATIVE PHYSICAL LIBRARY\n"
            );
        }

        if (! $clean) {
            return;
        }

        $managedDirectories = [
            '01_NORMES_CLASSEES',
            '02_AUTO_A_AUDITER',
            '90_A_VALIDER',
            '91_OCR_REQUIS',
            '92_NON_CLASSES',
        ];

        foreach ($managedDirectories as $name) {
            $directory = $destination.DIRECTORY_SEPARATOR.$name;

            if (File::exists($directory)) {
                try {
                    File::deleteDirectory($directory);
                } catch (\Throwable) {
                    // Si Windows verrouille un élément, la création idempotente
                    // continuera et les erreurs éventuelles seront journalisées.
                }
            }
        }
    }

    private function materialize(
        string $source,
        string $target,
        string $requestedMode,
        array &$stats
    ): string {
        if (! File::exists($source)) {
            throw new \RuntimeException('Source absente : '.$source);
        }

        File::ensureDirectoryExists(dirname($target));

        if (File::exists($target)) {
            $stats['existing']++;
            return 'existing';
        }

        if ($requestedMode === 'hardlink') {
            try {
                if (@link($source, $target)) {
                    $stats['hardlinks']++;
                    return 'hardlink';
                }
            } catch (\Throwable) {
                // fallback copy
            }
        }

        if (! File::copy($source, $target)) {
            throw new \RuntimeException(
                'Copie impossible : '.$source.' -> '.$target
            );
        }

        $stats['copies']++;

        return 'copy';
    }

    private function writeReadme(string $destination, string $runDir): void
    {
        $readme = $destination.DIRECTORY_SEPARATOR.'README_CIVILINA.txt';

        $text =
            "CIVILINA - BIBLIOTHEQUE NORMATIVE CLASSEE\n\n".
            "Structure :\n".
            "Discipline > Domaine > Sous-domaine > Reference > Edition > PDF\n\n".
            "01_NORMES_CLASSEES : VALIDATED ou audit SAFE\n".
            "02_AUTO_A_AUDITER : classification automatique non encore sure\n".
            "90_A_VALIDER       : validation humaine requise\n".
            "91_OCR_REQUIS      : PDF sans texte exploitable\n".
            "92_NON_CLASSES     : classement insuffisant\n\n".
            "Les originaux standards_inbox ne sont jamais deplaces.\n".
            "Dernier index cree : ".$runDir."\n";

        try {
            File::put($readme, $text);
        } catch (\Throwable) {
            // README éventuellement ouvert : n'empêche pas le classement.
        }
    }

    private function writeLatestPointer(
        string $indexRoot,
        string $runId,
        string $runDir
    ): void {
        File::ensureDirectoryExists($indexRoot);

        try {
            File::put(
                $indexRoot.DIRECTORY_SEPARATOR.'LATEST.txt',
                $runId."\n".$runDir."\n"
            );
        } catch (\Throwable) {
            // Non bloquant.
        }
    }
}
