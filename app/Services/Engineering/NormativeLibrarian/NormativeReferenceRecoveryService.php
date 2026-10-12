<?php

namespace App\Services\Engineering\NormativeLibrarian;

use App\Models\NormativeLibrarianDocument;
use Illuminate\Support\Str;

class NormativeReferenceRecoveryService
{
    /**
     * Récupère les références manquantes sans écraser une référence existante.
     *
     * Correctif 2K.3.8.1 :
     * les regex pouvaient extraire à la fois :
     *   NF DTU 20.1 P1-2
     *   DTU 20.1 P1-2
     * depuis la même chaîne et les considérer comme deux références concurrentes.
     *
     * Désormais les variantes imbriquées sont fusionnées et la référence la
     * plus complète est conservée : NF EN ISO > EN ISO > ISO, NF EN > EN,
     * NF DTU > DTU, NF ISO > ISO.
     *
     * @return array<string,int>
     */
    public function run(?int $limit = null): array
    {
        $stats = [
            'seen'=>0,
            'already_has_reference'=>0,
            'recovered'=>0,
            'review_candidates'=>0,
            'ambiguous'=>0,
            'no_match'=>0,
        ];

        $query = NormativeLibrarianDocument::query()
            ->where('document_kind', '!=', 'rejected')
            ->where(function ($q) {
                $q->whereNull('reference_code')
                    ->orWhere('reference_code', '');
            })
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        foreach ($query->get() as $document) {
            $stats['seen']++;

            if (trim((string)$document->reference_code) !== '') {
                $stats['already_has_reference']++;
                continue;
            }

            $result = $this->recover($document);

            $updates = [
                'reference_recovery_status'=>$result['status'],
                'reference_recovery_score'=>$result['score'],
                'reference_recovery_reason'=>$result['reason'],
                'reference_recovered_at'=>now(),
            ];

            if ($result['status'] === 'RECOVERED' && $result['reference']) {
                $updates['reference_code'] = $result['reference'];
                $updates['reference_normalized'] = preg_replace(
                    '/[^A-Z0-9]/',
                    '',
                    Str::upper(Str::ascii($result['reference']))
                );
                $stats['recovered']++;
            } elseif ($result['status'] === 'TO_REVIEW') {
                $stats['review_candidates']++;
            } elseif ($result['status'] === 'AMBIGUOUS') {
                $stats['ambiguous']++;
            } else {
                $stats['no_match']++;
            }

            $document->forceFill($updates)->save();
        }

        return $stats;
    }

    /**
     * @return array{status:string,reference:?string,score:float,reason:string,candidates:array}
     */
    public function recover(NormativeLibrarianDocument $document): array
    {
        $sources = [
            ['label'=>'filename','weight'=>50,'text'=>(string)$document->filename],
            ['label'=>'relative_path','weight'=>40,'text'=>(string)$document->relative_path],
            ['label'=>'title','weight'=>30,'text'=>(string)$document->title],
            ['label'=>'subtitle','weight'=>20,'text'=>(string)$document->subtitle],
            ['label'=>'cover_text','weight'=>45,'text'=>mb_substr((string)$document->cover_text,0,14000)],
            ['label'=>'ocr_cover','weight'=>85,'text'=>mb_substr((string)$document->ocr_text,0,6000)],
        ];

        $candidates = [];

        foreach ($sources as $source) {
            foreach ($this->extractReferences($source['text']) as $reference) {
                $key = $this->normalized($reference);

                if (! isset($candidates[$key])) {
                    $candidates[$key] = [
                        'reference'=>$reference,
                        'score'=>0,
                        'sources'=>[],
                    ];
                }

                if (! in_array($source['label'], $candidates[$key]['sources'], true)) {
                    $candidates[$key]['score'] += $source['weight'];
                    $candidates[$key]['sources'][] = $source['label'];
                }
            }
        }

        if (! $candidates) {
            return [
                'status'=>'NO_MATCH',
                'reference'=>null,
                'score'=>0,
                'reason'=>'Aucune référence normative détectable dans le nom, chemin, titre ou texte de couverture.',
                'candidates'=>[],
            ];
        }

        uasort($candidates, fn($a,$b) => $b['score'] <=> $a['score']);
        $ranked = array_values($candidates);
        $best = $ranked[0];
        $second = $ranked[1] ?? null;

        if ($second && $second['score'] >= $best['score'] * 0.80) {
            return [
                'status'=>'AMBIGUOUS',
                'reference'=>null,
                'score'=>(float)$best['score'],
                'reason'=>sprintf(
                    'Plusieurs références distinctes proches : %s (%d) / %s (%d).',
                    $best['reference'],
                    $best['score'],
                    $second['reference'],
                    $second['score']
                ),
                'candidates'=>$ranked,
            ];
        }

        $score = min(100, $best['score']);

        if ($score >= 80) {
            return [
                'status'=>'RECOVERED',
                'reference'=>$best['reference'],
                'score'=>(float)$score,
                'reason'=>'Référence retrouvée par concordance : '.implode(', ', $best['sources']).'.',
                'candidates'=>$ranked,
            ];
        }

        if ($score >= 50) {
            return [
                'status'=>'TO_REVIEW',
                'reference'=>$best['reference'],
                'score'=>(float)$score,
                'reason'=>'Candidat unique mais preuve insuffisante pour écriture automatique : '.implode(', ', $best['sources']).'.',
                'candidates'=>$ranked,
            ];
        }

        return [
            'status'=>'NO_MATCH',
            'reference'=>null,
            'score'=>(float)$score,
            'reason'=>'Score de récupération insuffisant.',
            'candidates'=>$ranked,
        ];
    }

    /**
     * @return array<int,string>
     */
    public function extractReferences(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $ascii = Str::upper(Str::ascii($value));
        $ascii = str_replace(['_','–','—'], [' ','-','-'], $ascii);

        /*
         * L'ordre va du plus spécifique au moins spécifique.
         * On collecte tout, puis collapseAliases() supprime les sous-références
         * produites par les regex imbriquées.
         */
        $patterns = [
            '/\bNF\s*DTU\s*\d+(?:\.\d+)?(?:\s*P\d+(?:-\d+)*)?\b/u',
            '/\bNF\s*EN\s*ISO\s*\d{2,6}(?:-\d+){0,4}\b/u',
            '/\bNF\s*EN\s*\d{2,6}(?:-\d+){0,4}\b/u',
            '/\bNF\s*ISO\s*\d{2,6}(?:-\d+){0,4}\b/u',
            '/\bEN\s*ISO\s*\d{2,6}(?:-\d+){0,4}\b/u',
            '/\bDTU\s*\d+(?:\.\d+)?(?:\s*P\d+(?:-\d+)*)?\b/u',
            '/\bEN\s*\d{2,6}(?:-\d+){0,4}\b/u',
            '/\bISO\s*\d{2,6}(?:-\d+){0,4}\b/u',
            '/\bNF\s*P\s*\d{1,3}-\d{2,4}(?:-\d+){0,3}\b/u',
            '/\bXP\s*P\s*\d{1,3}-\d{2,4}(?:-\d+){0,3}\b/u',
            '/\bP\s*\d{1,3}-\d{2,4}(?:-\d+){0,3}\b/u',
            '/\bGTR(?:\s*(?:19|20)\d{2})?\b/u',
            '/\bCFTR\s*\d{1,3}\b/u',
            '/\bDTR\s*[A-Z0-9.\-\/]+\b/u',
        ];

        $results = [];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $ascii, $matches)) {
                foreach ($matches[0] as $match) {
                    $results[] = $this->canonical($match);
                }
            }
        }

        return $this->collapseAliases($results);
    }

    /**
     * Supprime les alias imbriqués extraits du même contenu.
     *
     * Exemples :
     * NF DTU 20.1 P1-2 + DTU 20.1 P1-2 => NF DTU 20.1 P1-2
     * NF EN 1090-2 + EN 1090-2         => NF EN 1090-2
     * NF EN ISO 9606-1 + EN ISO 9606-1 + ISO 9606-1
     *                                  => NF EN ISO 9606-1
     *
     * Des références réellement différentes comme EN 206 et EN 12390
     * restent deux candidats distincts.
     *
     * @param array<int,string> $references
     * @return array<int,string>
     */
    public function collapseAliases(array $references): array
    {
        $unique = [];

        foreach ($references as $reference) {
            $key = $this->normalized($reference);

            if ($key !== '') {
                $unique[$key] = $reference;
            }
        }

        $keys = array_keys($unique);
        $remove = [];

        foreach ($keys as $key) {
            foreach ($keys as $other) {
                if ($key === $other) {
                    continue;
                }

                if ($this->isLessSpecificAlias($key, $other)) {
                    $remove[$key] = true;
                    break;
                }
            }
        }

        foreach (array_keys($remove) as $key) {
            unset($unique[$key]);
        }

        return array_values($unique);
    }

    /**
     * Retourne true si $candidate est une version moins spécifique de $specific.
     */
    private function isLessSpecificAlias(string $candidate, string $specific): bool
    {
        $prefixPairs = [
            ['candidate'=>'DTU',   'specific'=>'NFDTU'],
            ['candidate'=>'ENISO', 'specific'=>'NFENISO'],
            ['candidate'=>'ISO',   'specific'=>'NFENISO'],
            ['candidate'=>'ISO',   'specific'=>'ENISO'],
            ['candidate'=>'ISO',   'specific'=>'NFISO'],
            ['candidate'=>'EN',    'specific'=>'NFEN'],
            ['candidate'=>'P',     'specific'=>'NFP'],
            ['candidate'=>'P',     'specific'=>'XPP'],
        ];

        foreach ($prefixPairs as $pair) {
            if (
                str_starts_with($candidate, $pair['candidate'])
                && str_starts_with($specific, $pair['specific'])
            ) {
                $candidateBody = substr($candidate, strlen($pair['candidate']));
                $specificBody = substr($specific, strlen($pair['specific']));

                if ($candidateBody !== '' && $candidateBody === $specificBody) {
                    return true;
                }
            }
        }

        return false;
    }

    private function canonical(string $value): string
    {
        $value = Str::upper(Str::ascii(trim($value)));
        $value = preg_replace('/\s+/', ' ', $value);
        $value = preg_replace('/\s*-\s*/', '-', $value);

        $prefixes = [
            '/^NF\s*DTU\s*/'=>'NF DTU ',
            '/^NF\s*EN\s*ISO\s*/'=>'NF EN ISO ',
            '/^NF\s*EN\s*/'=>'NF EN ',
            '/^NF\s*ISO\s*/'=>'NF ISO ',
            '/^EN\s*ISO\s*/'=>'EN ISO ',
            '/^DTU\s*/'=>'DTU ',
            '/^EN\s*/'=>'EN ',
            '/^ISO\s*/'=>'ISO ',
            '/^NF\s*P\s*/'=>'NF P ',
            '/^XP\s*P\s*/'=>'XP P ',
            '/^P\s*/'=>'P ',
            '/^GTR\s*/'=>'GTR ',
            '/^CFTR\s*/'=>'CFTR ',
            '/^DTR\s*/'=>'DTR ',
        ];

        foreach ($prefixes as $pattern=>$replacement) {
            if (preg_match($pattern,$value)) {
                $value = preg_replace($pattern,$replacement,$value);
                break;
            }
        }

        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function normalized(string $value): string
    {
        return preg_replace(
            '/[^A-Z0-9]/',
            '',
            Str::upper(Str::ascii($value))
        );
    }
}
