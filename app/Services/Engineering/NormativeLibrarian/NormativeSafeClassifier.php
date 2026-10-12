<?php

namespace App\Services\Engineering\NormativeLibrarian;

use App\Models\NormativeLibrarianDocument;
use Illuminate\Support\Str;

class NormativeSafeClassifier
{
    /**
     * Classification pondérée.
     * Un mot générique seul ne suffit jamais pour AUTO_CLASSIFIED.
     *
     * @return array{
     *   discipline:?string,domain:?string,subdomain:?string,
     *   score:float,evidence:array<int,string>,conflicts:array<int,string>,reason:string
     * }
     */
    public function classify(NormativeLibrarianDocument $document): array
    {
        $text = $this->normalize(implode(' ', array_filter([
            $document->reference_code,
            $document->title,
            $document->subtitle,
            $document->category_label,
            $document->classification_index,
            implode(' ', (array) $document->ics_codes),
            $document->source_collection,
            $document->relative_path,
            mb_substr((string) $document->cover_text, 0, 14000),
        ])));

        $families = [
            [
                'discipline'=>'Bâtiment','domain'=>'CES','subdomain'=>'Maçonnerie',
                'strong'=>['nf dtu 20.1','dtu 20.1'],
                'phrases'=>['maconnerie','parois et murs','petits elements'],
                'weak'=>['brique','bloc beton','mortier de montage'],
            ],
            [
                'discipline'=>'Bâtiment','domain'=>'CES','subdomain'=>'Étanchéité',
                'strong'=>['nf dtu 43','dtu 43'],
                'phrases'=>['etancheite','toiture terrasse'],
                'weak'=>['membrane bitumineuse','pare vapeur'],
            ],
            [
                'discipline'=>'Bâtiment / Génie civil','domain'=>'Gros œuvre','subdomain'=>'Béton / béton armé',
                'strong'=>['nf en 206','en 206','nf en 12350','en 12350','nf en 12390','en 12390'],
                'phrases'=>['beton arme','beton frais','beton durci','essai de compression','armatures pour beton'],
                'weak'=>['beton','ciment','granulat','ferraillage','coffrage'],
            ],
            [
                'discipline'=>'Bâtiment / Génie civil','domain'=>'Structure métallique','subdomain'=>'Charpente métallique',
                'strong'=>['nf en 1090','en 1090'],
                'phrases'=>['execution des structures en acier','charpente metallique','assemblage boulonne'],
                'weak'=>['acier','boulon','serrage','poutre metallique'],
            ],
            [
                'discipline'=>'Bâtiment / Génie civil','domain'=>'Structure métallique','subdomain'=>'Soudage',
                'strong'=>['iso 9606','nf en iso 9606','iso 15614','nf en iso 15614','iso 17635','iso 17637'],
                'phrases'=>['qualification des soudeurs','mode operatoire de soudage','controle non destructif des soudures'],
                'weak'=>['soudage','soudure','welding','soudeur'],
            ],
            [
                'discipline'=>'Infrastructure','domain'=>'Routes & terrassements','subdomain'=>'Terrassements / compactage',
                'strong'=>['gtr 1992','gtr 2000','gtr 2023','guide des terrassements routiers'],
                'phrases'=>['terrassements routiers','compactage des remblais','classification des sols','essai proctor'],
                'weak'=>['gtr','terrassement','remblai','compactage','proctor','troxler','portance'],
            ],
            [
                'discipline'=>'Infrastructure','domain'=>'Routes & terrassements','subdomain'=>'Chaussées / enrobés',
                'strong'=>['cftr'],
                'phrases'=>['corps de chaussee','materiaux bitumineux','enrobes bitumineux','mesure de deflexion'],
                'weak'=>['chaussee','enrobe','bitume','deflexion'],
            ],
            [
                'discipline'=>'Management','domain'=>'Qualité','subdomain'=>'Système de management',
                'strong'=>['iso 9001','nf en iso 9001','iso 10005'],
                'phrases'=>['systeme de management de la qualite','plan qualite'],
                'weak'=>['management qualite','qualite'],
            ],
            [
                'discipline'=>'Bâtiment','domain'=>'CES','subdomain'=>'Plâtrerie / cloisons',
                'strong'=>['nf dtu 25','dtu 25'],
                'phrases'=>['ouvrages en plaques de platre','cloisons en plaques de platre'],
                'weak'=>['platrerie','plaque de platre','cloison'],
            ],
            [
                'discipline'=>'Bâtiment','domain'=>'CES','subdomain'=>'Façades / bardage',
                'strong'=>[],
                'phrases'=>['bardage rapporte','revetement exterieur de facade','facade rideau'],
                'weak'=>['facade','bardage','enveloppe'],
            ],
            [
                'discipline'=>'Bâtiment','domain'=>'CES','subdomain'=>'Plomberie',
                'strong'=>[],
                'phrases'=>['installations sanitaires','evacuation des eaux usees','alimentation en eau potable'],
                'weak'=>['plomberie','sanitaire','evacuation'],
            ],
            [
                'discipline'=>'Bâtiment','domain'=>'CES','subdomain'=>'Électricité',
                'strong'=>[],
                'phrases'=>['installations electriques des batiments','installations electriques basse tension'],
                'weak'=>['electricite','electrique','cable'],
            ],
            [
                'discipline'=>'Bâtiment','domain'=>'CES','subdomain'=>'CVC',
                'strong'=>[],
                'phrases'=>['ventilation des batiments','installations de climatisation','installations de chauffage'],
                'weak'=>['cvc','ventilation','climatisation','chauffage'],
            ],
            [
                'discipline'=>'Essais & laboratoire','domain'=>'Contrôle / essais','subdomain'=>'Métrologie / laboratoire',
                'strong'=>[],
                'phrases'=>['laboratoires d essais','exigences pour les laboratoires','etalonnage des instruments'],
                'weak'=>['metrologie','etalonnage','laboratoire','echantillonnage'],
            ],
        ];

        $results = [];

        foreach ($families as $family) {
            $score = 0;
            $evidence = [];

            foreach ($family['strong'] as $term) {
                if ($this->contains($text, $term)) {
                    $score += 65;
                    $evidence[] = 'référence forte: '.$term;
                    break;
                }
            }

            $phraseHits = 0;
            foreach ($family['phrases'] as $term) {
                if ($this->contains($text, $term)) {
                    $phraseHits++;
                    $evidence[] = 'expression métier: '.$term;
                }
            }
            $score += min(35, $phraseHits * 20);

            $weakHits = 0;
            foreach ($family['weak'] as $term) {
                if ($this->contains($text, $term)) {
                    $weakHits++;
                    $evidence[] = 'indice faible: '.$term;
                }
            }
            $score += min(20, $weakHits * 7);

            // Un seul indice faible ne doit pas classer automatiquement.
            if ($score <= 7) {
                $score = 0;
                $evidence = [];
            }

            if ($score > 0) {
                $results[] = [
                    'discipline'=>$family['discipline'],
                    'domain'=>$family['domain'],
                    'subdomain'=>$family['subdomain'],
                    'score'=>min(100, $score),
                    'evidence'=>$evidence,
                ];
            }
        }

        usort($results, fn($a,$b) => $b['score'] <=> $a['score']);

        if (! $results) {
            return [
                'discipline'=>null,'domain'=>null,'subdomain'=>null,
                'score'=>0,'evidence'=>[],'conflicts'=>[],
                'reason'=>'Aucun signal métier fiable.',
            ];
        }

        $best = $results[0];
        $conflicts = [];

        foreach (array_slice($results, 1, 0 + 4) as $other) {
            if ($other['score'] >= 45 && $other['domain'] !== $best['domain']) {
                $conflicts[] = sprintf(
                    '%s/%s=%d',
                    $other['domain'],
                    $other['subdomain'],
                    $other['score']
                );
            }
        }

        if ($conflicts) {
            $best['score'] = max(0, $best['score'] - 25);
        }

        return [
            'discipline'=>$best['discipline'],
            'domain'=>$best['domain'],
            'subdomain'=>$best['subdomain'],
            'score'=>(float) min(100, $best['score']),
            'evidence'=>$best['evidence'],
            'conflicts'=>$conflicts,
            'reason'=>sprintf(
                'Score sûr=%d ; %s%s',
                $best['score'],
                implode(' | ', $best['evidence']),
                $conflicts ? ' ; conflits='.implode(', ', $conflicts) : ''
            ),
        ];
    }

    private function normalize(string $value): string
    {
        $value = Str::lower(Str::ascii($value));
        $value = str_replace(['_', '\\', '/', '-', '–', '—'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    private function contains(string $text, string $term): bool
    {
        return str_contains($text, $this->normalize($term));
    }
}
