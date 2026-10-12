<?php

namespace App\Services\Engineering\Normative;

use App\Models\EngineeringNormativeEdition;
use App\Models\EngineeringNormativeRequirement;
use App\Models\EngineeringNormativeSection;
use App\Models\EngineeringNormativeSource;
use App\Models\EngineeringNormativeStandard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class NormativeRegistryService
{
    public function __construct(
        private NormativeReferenceNormalizer $normalizer,
        private NormativeDocumentClassifier $classifier,
        private NormativeTextExtractor $extractor,
        private NormativeSectionParser $sectionParser,
        private NormativeRequirementExtractor $requirementExtractor,
        private NormativeDomainClassifier $domainClassifier,
    ) {
    }

    /**
     * @return array<string,int>
     */
    public function scan(?int $limit = null, bool $force = false): array
    {
        $inbox = (string) config('civilina_normative.inbox');
        $textDir = (string) config('civilina_normative.raw_text_dir');

        File::ensureDirectoryExists($inbox);
        File::ensureDirectoryExists($textDir);

        $stats = [
            'seen' => 0,
            'indexed' => 0,
            'duplicates' => 0,
            'rejected' => 0,
            'needs_parser' => 0,
            'errors' => 0,
            'skipped' => 0,
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($inbox, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $extension = strtolower($file->getExtension());
            if (! in_array($extension, ['pdf', 'docx', 'txt', 'md'], true)) {
                continue;
            }

            if ($limit !== null && $stats['seen'] >= $limit) {
                break;
            }

            $stats['seen']++;

            try {
                $result = $this->indexFile($file->getPathname(), $inbox, $textDir, $force);
                $stats[$result] = ($stats[$result] ?? 0) + 1;
            } catch (\Throwable $e) {
                report($e);
                $stats['errors']++;
            }
        }

        return $stats;
    }

    public function indexFile(string $path, string $inbox, string $textDir, bool $force = false): string
    {
        $relative = ltrim(str_replace('\\', '/', Str::after($path, rtrim($inbox, '\\/'))), '/');
        $size = filesize($path) ?: 0;
        $mtime = filemtime($path) ?: time();

        $existing = EngineeringNormativeSource::query()->where('relative_path', $relative)->first();
        if ($existing && ! $force && $existing->source_modified_at?->timestamp === $mtime && $existing->size_bytes === $size && $existing->indexed_at) {
            return 'skipped';
        }

        $sha = hash_file('sha256', $path) ?: null;

        $exactDuplicate = $sha
            ? EngineeringNormativeSource::query()
                ->where('sha256', $sha)
                ->where('relative_path', '!=', $relative)
                ->whereNull('duplicate_of_source_id')
                ->first()
            : null;

        if ($exactDuplicate) {
            EngineeringNormativeSource::query()->updateOrCreate(
                ['relative_path' => $relative],
                [
                    'standard_id' => $exactDuplicate->standard_id,
                    'duplicate_of_source_id' => $exactDuplicate->id,
                    'filename' => basename($path),
                    'extension' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                    'sha256' => $sha,
                    'size_bytes' => $size,
                    'source_modified_at' => date('Y-m-d H:i:s', $mtime),
                    'detected_code' => $exactDuplicate->detected_code,
                    'detected_title' => $exactDuplicate->detected_title,
                    'document_type' => $exactDuplicate->document_type,
                    'extraction_status' => 'exact_duplicate',
                    'is_exact_duplicate' => true,
                    'indexed_at' => now(),
                ]
            );

            return 'duplicates';
        }

        $extracted = $this->extractor->extract($path);
        $text = (string) ($extracted['text'] ?? '');

        $reference = $this->normalizer->detect(basename($path), $text);
        $classification = $this->classifier->classify(
            basename($path),
            $reference['code'],
            mb_substr($text, 0, 3000)
        );

        $title = $this->titleFromFilename(basename($path), $reference['code']);
        $domain = $this->domainClassifier->detect(basename($path).' '.$title.' '.mb_substr($text, 0, 12000));

        $source = EngineeringNormativeSource::query()->updateOrCreate(
            ['relative_path' => $relative],
            [
                'filename' => basename($path),
                'extension' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                'sha256' => $sha,
                'size_bytes' => $size,
                'source_modified_at' => date('Y-m-d H:i:s', $mtime),
                'detected_code' => $reference['code'],
                'detected_title' => $title,
                'document_type' => $classification['type'],
                'extraction_status' => $classification['rejected'] ? 'rejected' : $extracted['status'],
                'is_exact_duplicate' => false,
                'rejection_reason' => $classification['reason'],
                'metadata' => [
                    'extract_message' => $extracted['message'] ?? null,
                    'detected_year' => $reference['year'],
                    'family_code' => $reference['family'],
                ],
                'indexed_at' => now(),
            ]
        );

        if ($classification['rejected']) {
            return 'rejected';
        }

        if ($classification['type'] === 'technical_sheet' || $classification['type'] === 'course') {
            return 'indexed';
        }

        if ($reference['code'] === null) {
            return $extracted['status'] === 'needs_pdf_parser' ? 'needs_parser' : 'indexed';
        }

        DB::transaction(function () use ($source, $reference, $title, $domain, $classification, $extracted, $textDir) {
            $standard = EngineeringNormativeStandard::query()->firstOrCreate(
                ['normalized_code' => $reference['normalized']],
                [
                    'canonical_code' => $reference['code'],
                    'family_code' => $reference['family'],
                    'title' => $title,
                    'issuing_body' => $reference['body'],
                    'domain' => $domain,
                    'document_type' => $classification['type'],
                    'status' => 'active',
                ]
            );

            $updates = [];
            if (! $standard->title && $title) {
                $updates['title'] = $title;
            }
            if (! $standard->domain && $domain) {
                $updates['domain'] = $domain;
            }
            if ($updates) {
                $standard->update($updates);
            }

            $source->update(['standard_id' => $standard->id]);

            $edition = EngineeringNormativeEdition::query()->firstOrCreate(
                [
                    'standard_id' => $standard->id,
                    'publication_year' => $reference['year'],
                    'adoption_code' => $reference['code'],
                ],
                [
                    'source_id' => $source->id,
                    'edition_label' => $reference['year'] ? (string) $reference['year'] : 'édition non détectée',
                    'is_current' => false,
                ]
            );

            if (($extracted['status'] ?? null) !== 'extracted' || trim((string) ($extracted['text'] ?? '')) === '') {
                return;
            }

            $textPath = rtrim($textDir, '\\/').DIRECTORY_SEPARATOR.'source_'.$source->id.'.txt';
            file_put_contents($textPath, $extracted['text']);
            $source->update(['text_path' => $textPath]);

            $edition->sections()->delete();

            $sections = $this->sectionParser->parse((array) ($extracted['pages'] ?? []));
            foreach ($sections as $sectionData) {
                $body = mb_substr($sectionData['body'], 0, 50000);

                $section = EngineeringNormativeSection::query()->create([
                    'edition_id' => $edition->id,
                    'source_id' => $source->id,
                    'section_code' => $sectionData['section_code'],
                    'heading' => $sectionData['heading'],
                    'body' => $body,
                    'body_hash' => hash('sha256', $body),
                    'page_number' => $sectionData['page_number'],
                    'sequence' => $sectionData['sequence'],
                ]);

                foreach ($this->requirementExtractor->extract($body) as $requirement) {
                    EngineeringNormativeRequirement::query()->create(
                        array_merge($requirement, ['section_id' => $section->id])
                    );
                }
            }
        });

        return $extracted['status'] === 'needs_pdf_parser' ? 'needs_parser' : 'indexed';
    }

    private function titleFromFilename(string $filename, ?string $code): string
    {
        $title = pathinfo($filename, PATHINFO_FILENAME);
        $title = str_replace(['_', '...'], ' ', $title);
        $title = preg_replace('/^(norme|standard|norme\s+[A-Z]\s*\d+)\s*/ui', '', $title);

        if ($code) {
            $title = str_ireplace($code, '', $title);
            $normalized = preg_replace('/\s+/', '', $code);
            $title = str_ireplace($normalized, '', preg_replace('/\s+/', '', $title));
        }

        $title = trim(preg_replace('/[\s\.\-_]+/', ' ', $title));
        return mb_substr($title, 0, 250);
    }
}
