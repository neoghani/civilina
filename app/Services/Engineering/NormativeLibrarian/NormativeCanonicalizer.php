<?php

namespace App\Services\Engineering\NormativeLibrarian;

use App\Models\NormativeLibrarianDocument;
use Illuminate\Support\Str;

class NormativeCanonicalizer
{
    /**
     * Regroupe les copies d'une même norme et sépare les éditions.
     *
     * canonical_group_key = référence normalisée
     * edition_key         = référence + année
     *
     * Une édition différente n'est jamais considérée comme doublon.
     *
     * @return array<string,int>
     */
    public function run(?int $limit = null): array
    {
        $stats = [
            'seen'=>0,
            'with_reference'=>0,
            'groups'=>0,
            'editions'=>0,
            'canonical_assigned'=>0,
            'source_copies'=>0,
            'no_reference'=>0,
        ];

        $query = NormativeLibrarianDocument::query()
            ->where('document_kind', '!=', 'rejected')
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $documents = $query->get();
        $stats['seen'] = $documents->count();

        $groups = [];

        foreach ($documents as $document) {
            $reference = $this->normalizedReference($document);

            if (! $reference) {
                $stats['no_reference']++;
                continue;
            }

            $stats['with_reference']++;

            $edition = $this->editionKey($document, $reference);

            $groups[$reference][$edition][] = $document;
        }

        $stats['groups'] = count($groups);

        foreach ($groups as $reference => $editions) {
            foreach ($editions as $editionKey => $editionDocuments) {
                $stats['editions']++;

                $canonical = $this->chooseCanonical($editionDocuments);

                foreach ($editionDocuments as $document) {
                    $isCanonical = (int)$document->id === (int)$canonical->id;

                    $document->forceFill([
                        'canonical_group_key'=>$reference,
                        'edition_key'=>$editionKey,
                        'canonical_document_id'=>$canonical->id,
                        'version_status'=>$isCanonical ? 'CANONICAL' : 'SOURCE_COPY',
                    ])->save();

                    if ($isCanonical) {
                        $stats['canonical_assigned']++;
                    } else {
                        $stats['source_copies']++;
                    }
                }
            }
        }

        return $stats;
    }

    public function normalizedReference(NormativeLibrarianDocument $document): ?string
    {
        $value = trim((string)($document->reference_normalized ?: $document->reference_code));

        if ($value === '') {
            return null;
        }

        $value = Str::upper(Str::ascii($value));
        $value = preg_replace('/[^A-Z0-9]/', '', $value);

        return $value !== '' ? $value : null;
    }

    public function editionKey(NormativeLibrarianDocument $document, string $reference): string
    {
        $year = $document->publication_year ?: 'ANNEE_INCONNUE';

        return $reference.'__'.$year;
    }

    /**
     * @param array<int,NormativeLibrarianDocument> $documents
     */
    private function chooseCanonical(array $documents): NormativeLibrarianDocument
    {
        usort($documents, function (NormativeLibrarianDocument $a, NormativeLibrarianDocument $b) {
            $scoreA = $this->qualityScore($a);
            $scoreB = $this->qualityScore($b);

            if ($scoreA === $scoreB) {
                return $a->id <=> $b->id;
            }

            return $scoreB <=> $scoreA;
        });

        return $documents[0];
    }

    private function qualityScore(NormativeLibrarianDocument $document): int
    {
        $score = 0;

        if ($document->classification_status === 'VALIDATED') $score += 1000;
        if ($document->audit_status === 'SAFE') $score += 500;
        if ($document->classification_status === 'AUTO_CLASSIFIED') $score += 200;
        if ($document->extraction_status === 'extracted') $score += 100;
        if ($document->title) $score += 30;
        if ($document->subtitle) $score += 15;
        if ($document->publication_year) $score += 20;
        if ($document->classification_index) $score += 10;
        if ($document->ics_codes) $score += 10;
        if (! $document->duplicate_of_id) $score += 50;

        return $score;
    }
}
