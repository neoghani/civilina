<?php

namespace App\Services\Engineering\NormativeLibrarian;

use App\Models\NormativeLibrarianDocument;
use Illuminate\Support\Str;

class NormativeClassificationRefiner
{
    /**
     * Deuxième passe de classement.
     *
     * Règles :
     * - ne touche jamais un document VALIDATED ;
     * - ne déplace aucun fichier ;
     * - utilise référence + titre + sous-titre + chemin + collection + texte couverture + ICS ;
     * - ne change pas une donnée si la règle n'apporte pas un niveau de confiance suffisant.
     *
     * @return array<string,int>
     */
    public function run(int $limit = 500, string $status = 'UNCLASSIFIED'): array
    {
        $stats = [
            'seen' => 0,
            'upgraded_auto' => 0,
            'upgraded_review' => 0,
            'unchanged' => 0,
            'skipped_validated' => 0,
        ];

        NormativeLibrarianDocument::query()
            ->where('classification_status', $status)
            ->where('document_kind', '!=', 'rejected')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (NormativeLibrarianDocument $document) use (&$stats) {
                $stats['seen']++;

                if ($document->classification_status === 'VALIDATED') {
                    $stats['skipped_validated']++;
                    return;
                }

                $result = $this->classify($document);

                if (! $result['discipline'] || ! $result['domain']) {
                    $stats['unchanged']++;
                    return;
                }

                $currentConfidence = (float) $document->confidence;
                $newConfidence = max($currentConfidence, $result['confidence']);

                $newStatus = $newConfidence >= 80
                    ? 'AUTO_CLASSIFIED'
                    : ($newConfidence >= 55 ? 'TO_REVIEW' : $document->classification_status);

                $document->forceFill([
                    'discipline' => $result['discipline'],
                    'domain' => $result['domain'],
                    'subdomain' => $result['subdomain'],
                    'confidence' => $newConfidence,
                    'classification_status' => $newStatus,
                    'review_notes' => $this->appendNote(
                        $document->review_notes,
                        '[Second pass] '.$result['reason']
                    ),
                    'metadata' => array_merge((array) $document->metadata, [
                        'second_pass' => [
                            'reason' => $result['reason'],
                            'score' => $result['confidence'],
                            'at' => now()->toIso8601String(),
                        ],
                    ]),
                ])->save();

                if ($newStatus === 'AUTO_CLASSIFIED') {
                    $stats['upgraded_auto']++;
                } elseif ($newStatus === 'TO_REVIEW') {
                    $stats['upgraded_review']++;
                } else {
                    $stats['unchanged']++;
                }
            });

        return $stats;
    }

    /**
     * @return array{discipline:?string,domain:?string,subdomain:?string,confidence:float,reason:string}
     */
    public function classify(NormativeLibrarianDocument $document): array
    {
        $haystack = $this->normalize(implode(' ', array_filter([
            $document->reference_code,
            $document->title,
            $document->subtitle,
            $document->category_label,
            $document->classification_index,
            implode(' ', (array) $document->ics_codes),
            $document->source_collection,
            $document->relative_path,
            mb_substr((string) $document->cover_text, 0, 12000),
        ])));

        $rules = [
            [
                'score' => 96,
                'discipline' => 'Bâtiment',
                'domain' => 'CES',
                'subdomain' => 'Maçonnerie',
                'terms' => ['nf dtu 20.1', 'maconnerie', 'parois et murs', 'petits elements'],
                'reason' => 'Famille maçonnerie / NF DTU 20.1 détectée.',
            ],
            [
                'score' => 94,
                'discipline' => 'Bâtiment',
                'domain' => 'CES',
                'subdomain' => 'Étanchéité',
                'terms' => ['nf dtu 43', 'dtu 43', 'etancheite', 'toiture terrasse'],
                'reason' => 'Famille étanchéité / DTU 43 détectée.',
            ],
            [
                'score' => 93,
                'discipline' => 'Bâtiment / Génie civil',
                'domain' => 'Gros œuvre',
                'subdomain' => 'Béton / béton armé',
                'terms' => ['nf en 206', 'en 206', 'beton', 'beton arme', '12350', '12390'],
                'reason' => 'Référentiel béton / essais béton détecté.',
            ],
            [
                'score' => 93,
                'discipline' => 'Bâtiment / Génie civil',
                'domain' => 'Structure métallique',
                'subdomain' => 'Charpente métallique',
                'terms' => ['en 1090', 'nf en 1090', 'charpente metallique', 'execution des structures en acier', 'acier'],
                'reason' => 'Référentiel structure métallique / EN 1090 détecté.',
            ],
            [
                'score' => 94,
                'discipline' => 'Bâtiment / Génie civil',
                'domain' => 'Structure métallique',
                'subdomain' => 'Soudage',
                'terms' => ['iso 9606', 'iso 15614', 'soudage', 'soudure', 'welding'],
                'reason' => 'Référentiel soudage / qualification détecté.',
            ],
            [
                'score' => 95,
                'discipline' => 'Infrastructure',
                'domain' => 'Routes & terrassements',
                'subdomain' => 'Terrassements / compactage',
                'terms' => ['gtr', 'terrassement', 'remblai', 'compactage', 'proctor', 'troxler'],
                'reason' => 'Référentiel terrassements / compactage détecté.',
            ],
            [
                'score' => 92,
                'discipline' => 'Infrastructure',
                'domain' => 'Routes & terrassements',
                'subdomain' => 'Chaussées / enrobés',
                'terms' => ['cftr', 'chaussee', 'corps de chaussee', 'enrobe', 'bitume', 'deflexion'],
                'reason' => 'Référentiel chaussées / enrobés détecté.',
            ],
            [
                'score' => 92,
                'discipline' => 'Management',
                'domain' => 'Qualité',
                'subdomain' => 'Système de management',
                'terms' => ['iso 9001', 'iso 10005', 'management de la qualite', 'systeme de management'],
                'reason' => 'Référentiel management qualité détecté.',
            ],
            [
                'score' => 88,
                'discipline' => 'Bâtiment',
                'domain' => 'CES',
                'subdomain' => 'Plâtrerie / cloisons',
                'terms' => ['platrerie', 'plaque de platre', 'cloison', 'dtu 25'],
                'reason' => 'Famille plâtrerie / cloisons détectée.',
            ],
            [
                'score' => 88,
                'discipline' => 'Bâtiment',
                'domain' => 'CES',
                'subdomain' => 'Façades / bardage',
                'terms' => ['facade', 'bardage', 'enveloppe'],
                'reason' => 'Famille façades / bardage détectée.',
            ],
            [
                'score' => 87,
                'discipline' => 'Bâtiment',
                'domain' => 'CES',
                'subdomain' => 'Plomberie',
                'terms' => ['plomberie', 'sanitaire', 'evacuation', 'alimentation eau'],
                'reason' => 'Famille plomberie détectée.',
            ],
            [
                'score' => 87,
                'discipline' => 'Bâtiment',
                'domain' => 'CES',
                'subdomain' => 'Électricité',
                'terms' => ['electricite', 'installation electrique', 'cable electrique'],
                'reason' => 'Famille électricité détectée.',
            ],
            [
                'score' => 87,
                'discipline' => 'Bâtiment',
                'domain' => 'CES',
                'subdomain' => 'CVC',
                'terms' => ['cvc', 'ventilation', 'climatisation', 'chauffage'],
                'reason' => 'Famille CVC détectée.',
            ],
            [
                'score' => 83,
                'discipline' => 'Essais & laboratoire',
                'domain' => 'Contrôle / essais',
                'subdomain' => 'Métrologie / laboratoire',
                'terms' => ['metrologie', 'etalonnage', 'laboratoire', 'echantillonnage'],
                'reason' => 'Famille essais / laboratoire / métrologie détectée.',
            ],
        ];

        $best = null;

        foreach ($rules as $rule) {
            $hits = 0;

            foreach ($rule['terms'] as $term) {
                if (str_contains($haystack, $this->normalize($term))) {
                    $hits++;
                }
            }

            if ($hits === 0) {
                continue;
            }

            $score = min(100, $rule['score'] + max(0, $hits - 1) * 2);

            if (! $best || $score > $best['confidence']) {
                $best = [
                    'discipline' => $rule['discipline'],
                    'domain' => $rule['domain'],
                    'subdomain' => $rule['subdomain'],
                    'confidence' => $score,
                    'reason' => $rule['reason'].' Hits='.$hits,
                ];
            }
        }

        return $best ?? [
            'discipline' => null,
            'domain' => null,
            'subdomain' => null,
            'confidence' => (float) $document->confidence,
            'reason' => 'Aucune règle de deuxième passe suffisamment fiable.',
        ];
    }

    private function normalize(string $value): string
    {
        $value = Str::lower(Str::ascii($value));
        $value = str_replace(['_', '\\', '/', '-'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    private function appendNote(?string $existing, string $note): string
    {
        $existing = trim((string) $existing);

        return $existing === '' ? $note : $existing."\n".$note;
    }
}
