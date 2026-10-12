<?php

namespace App\Services\Engineering\NormativeLibrarian;

use App\Models\NormativeLibrarianDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NormativeCanonicalConsensusService
{
    /**
     * Applique uniquement des promotions prudentes :
     * 1. une validation ingénieur dans la même référence/édition domine ;
     * 2. une famille normative forte et explicite peut classer ;
     * 3. sinon il faut un consensus de plusieurs sources sans conflit.
     *
     * @return array<string,int>
     */
    public function run(?int $limitGroups = null): array
    {
        $stats = [
            'groups_seen'=>0,
            'validated_propagated'=>0,
            'strong_reference_promoted'=>0,
            'consensus_promoted'=>0,
            'conflicts'=>0,
            'insufficient'=>0,
            'documents_updated'=>0,
        ];

        $groupKeys = NormativeLibrarianDocument::query()
            ->whereNotNull('canonical_group_key')
            ->whereNotNull('edition_key')
            ->where('document_kind', '!=', 'rejected')
            ->select(['canonical_group_key','edition_key'])
            ->distinct()
            ->orderBy('canonical_group_key');

        if ($limitGroups !== null) {
            $groupKeys->limit($limitGroups);
        }

        foreach ($groupKeys->get() as $key) {
            $stats['groups_seen']++;

            $documents = NormativeLibrarianDocument::query()
                ->where('canonical_group_key', $key->canonical_group_key)
                ->where('edition_key', $key->edition_key)
                ->where('document_kind', '!=', 'rejected')
                ->orderBy('id')
                ->get();

            $result = $this->classifyGroup($documents);

            if ($result['status'] === 'CONFLICT') {
                $stats['conflicts']++;
                $this->markGroup($documents, $result);
                continue;
            }

            if ($result['status'] === 'INSUFFICIENT') {
                $stats['insufficient']++;
                $this->markGroup($documents, $result);
                continue;
            }

            if ($result['status'] === 'VALIDATED_PROPAGATION') {
                $stats['validated_propagated']++;
            } elseif ($result['status'] === 'STRONG_REFERENCE') {
                $stats['strong_reference_promoted']++;
            } elseif ($result['status'] === 'CONSENSUS_SAFE') {
                $stats['consensus_promoted']++;
            }

            foreach ($documents as $document) {
                // Une validation humaine existante reste intouchable.
                if ($document->classification_status === 'VALIDATED') {
                    $document->forceFill([
                        'consensus_status'=>$result['status'],
                        'consensus_score'=>$result['score'],
                        'consensus_reason'=>$result['reason'],
                        'consensus_at'=>now(),
                    ])->save();
                    continue;
                }

                $updates = [
                    'discipline'=>$result['discipline'],
                    'domain'=>$result['domain'],
                    'subdomain'=>$result['subdomain'],
                    'consensus_status'=>$result['status'],
                    'consensus_score'=>$result['score'],
                    'consensus_reason'=>$result['reason'],
                    'consensus_at'=>now(),
                ];

                /*
                 * STRONG_REFERENCE / VALIDATED_PROPAGATION :
                 * assez fiable pour passer SAFE.
                 *
                 * CONSENSUS_SAFE :
                 * il faut score >= 90 et absence de conflit.
                 */
                if (
                    in_array($result['status'], ['VALIDATED_PROPAGATION','STRONG_REFERENCE'], true)
                    || ($result['status'] === 'CONSENSUS_SAFE' && $result['score'] >= 90)
                ) {
                    $updates['classification_status'] = 'AUTO_CLASSIFIED';
                    $updates['audit_status'] = 'SAFE';
                    $updates['audit_risk'] = max(0, 100 - $result['score']);
                    $updates['audit_reason'] = 'Consensus canonique : '.$result['reason'];
                    $updates['confidence'] = max(
                        (float)$document->confidence,
                        (float)$result['score']
                    );
                } else {
                    $updates['classification_status'] = 'TO_REVIEW';
                }

                $document->forceFill($updates)->save();
                $stats['documents_updated']++;
            }
        }

        return $stats;
    }

    /**
     * @param Collection<int,NormativeLibrarianDocument> $documents
     * @return array{
     *   status:string,score:float,discipline:?string,domain:?string,
     *   subdomain:?string,reason:string
     * }
     */
    public function classifyGroup(Collection $documents): array
    {
        if ($documents->isEmpty()) {
            return $this->result('INSUFFICIENT',0,null,null,null,'Groupe vide.');
        }

        $validated = $documents->firstWhere('classification_status','VALIDATED');

        if ($validated && $validated->discipline && $validated->domain) {
            return $this->result(
                'VALIDATED_PROPAGATION',
                100,
                $validated->discipline,
                $validated->domain,
                $validated->subdomain,
                'Une fiche de cette référence/édition a été validée par un ingénieur.'
            );
        }

        $reference = (string)($documents->first()->reference_code ?: '');
        $strong = $this->strongReferenceMap($reference);

        if ($strong) {
            return $this->result(
                'STRONG_REFERENCE',
                $strong['score'],
                $strong['discipline'],
                $strong['domain'],
                $strong['subdomain'],
                'Famille normative forte reconnue : '.$strong['family']
            );
        }

        $votes = [];

        foreach ($documents as $document) {
            if (! $document->discipline || ! $document->domain) {
                continue;
            }

            $key = implode('|', [
                $document->discipline,
                $document->domain,
                $document->subdomain ?: '',
            ]);

            $weight = 0;

            if ($document->audit_status === 'SAFE') $weight += 5;
            if ($document->classification_status === 'AUTO_CLASSIFIED') $weight += 3;
            if ((float)$document->confidence >= 90) $weight += 3;
            elseif ((float)$document->confidence >= 80) $weight += 2;
            elseif ((float)$document->confidence >= 60) $weight += 1;

            if ($document->extraction_status === 'extracted') $weight += 1;
            if ($document->reference_code) $weight += 1;
            if ($document->title) $weight += 1;

            if (! isset($votes[$key])) {
                $votes[$key] = [
                    'count'=>0,
                    'weight'=>0,
                    'discipline'=>$document->discipline,
                    'domain'=>$document->domain,
                    'subdomain'=>$document->subdomain,
                ];
            }

            $votes[$key]['count']++;
            $votes[$key]['weight'] += $weight;
        }

        if (! $votes) {
            return $this->result(
                'INSUFFICIENT',0,null,null,null,
                'Aucun classement exploitable dans les sources du groupe.'
            );
        }

        uasort($votes, function ($a,$b) {
            if ($a['weight'] === $b['weight']) {
                return $b['count'] <=> $a['count'];
            }
            return $b['weight'] <=> $a['weight'];
        });

        $ranked = array_values($votes);
        $best = $ranked[0];
        $second = $ranked[1] ?? null;

        if (
            $second
            && $second['weight'] >= max(4, (int)floor($best['weight'] * 0.70))
            && $second['domain'] !== $best['domain']
        ) {
            return $this->result(
                'CONFLICT',
                0,
                null,
                null,
                null,
                sprintf(
                    'Conflit de classement : %s/%s poids=%d contre %s/%s poids=%d.',
                    $best['domain'],
                    $best['subdomain'] ?: '—',
                    $best['weight'],
                    $second['domain'],
                    $second['subdomain'] ?: '—',
                    $second['weight']
                )
            );
        }

        /*
         * On exige au moins deux sources concordantes OU une source SAFE.
         */
        $hasSafe = $documents->contains(fn ($d) =>
            $d->audit_status === 'SAFE'
            && $d->domain === $best['domain']
            && $d->subdomain === $best['subdomain']
        );

        if ($best['count'] < 2 && ! $hasSafe) {
            return $this->result(
                'INSUFFICIENT',
                0,
                null,
                null,
                null,
                'Une seule source non validée : consensus insuffisant.'
            );
        }

        $score = min(
            98,
            70
            + min(18, $best['count'] * 6)
            + min(10, $best['weight'])
        );

        return $this->result(
            'CONSENSUS_SAFE',
            $score,
            $best['discipline'],
            $best['domain'],
            $best['subdomain'],
            sprintf(
                'Consensus de %d source(s), poids=%d, sans conflit métier.',
                $best['count'],
                $best['weight']
            )
        );
    }

    private function strongReferenceMap(string $reference): ?array
    {
        $r = Str::upper(Str::ascii($reference));
        $r = preg_replace('/[^A-Z0-9.]/','',$r);

        $rules = [
            ['pattern'=>'/^NFDTU20\.1/', 'd'=>'Bâtiment','m'=>'CES','s'=>'Maçonnerie','f'=>'NF DTU 20.1','score'=>98],
            ['pattern'=>'/^DTU20\.1/', 'd'=>'Bâtiment','m'=>'CES','s'=>'Maçonnerie','f'=>'DTU 20.1','score'=>97],
            ['pattern'=>'/^NFDTU43/', 'd'=>'Bâtiment','m'=>'CES','s'=>'Étanchéité','f'=>'NF DTU 43','score'=>97],
            ['pattern'=>'/^DTU43/', 'd'=>'Bâtiment','m'=>'CES','s'=>'Étanchéité','f'=>'DTU 43','score'=>96],
            ['pattern'=>'/(^|NF)EN206/', 'd'=>'Bâtiment / Génie civil','m'=>'Gros œuvre','s'=>'Béton / béton armé','f'=>'EN 206','score'=>98],
            ['pattern'=>'/(^|NF)EN12350/', 'd'=>'Bâtiment / Génie civil','m'=>'Gros œuvre','s'=>'Béton / béton armé','f'=>'EN 12350','score'=>98],
            ['pattern'=>'/(^|NF)EN12390/', 'd'=>'Bâtiment / Génie civil','m'=>'Gros œuvre','s'=>'Béton / béton armé','f'=>'EN 12390','score'=>98],
            ['pattern'=>'/(^|NF)EN1090/', 'd'=>'Bâtiment / Génie civil','m'=>'Structure métallique','s'=>'Charpente métallique','f'=>'EN 1090','score'=>98],
            ['pattern'=>'/ISO9606/', 'd'=>'Bâtiment / Génie civil','m'=>'Structure métallique','s'=>'Soudage','f'=>'ISO 9606','score'=>98],
            ['pattern'=>'/ISO15614/', 'd'=>'Bâtiment / Génie civil','m'=>'Structure métallique','s'=>'Soudage','f'=>'ISO 15614','score'=>98],
            ['pattern'=>'/ISO1763[5-9]|ISO1764[0-1]/', 'd'=>'Bâtiment / Génie civil','m'=>'Structure métallique','s'=>'Soudage','f'=>'ISO 17635-17641','score'=>96],
            ['pattern'=>'/ISO9001/', 'd'=>'Management','m'=>'Qualité','s'=>'Système de management','f'=>'ISO 9001','score'=>98],
            ['pattern'=>'/ISO10005/', 'd'=>'Management','m'=>'Qualité','s'=>'Système de management','f'=>'ISO 10005','score'=>98],
            ['pattern'=>'/^GTR/', 'd'=>'Infrastructure','m'=>'Routes & terrassements','s'=>'Terrassements / compactage','f'=>'GTR','score'=>96],
            ['pattern'=>'/^CFTR/', 'd'=>'Infrastructure','m'=>'Routes & terrassements','s'=>'Chaussées / enrobés','f'=>'CFTR','score'=>95],
        ];

        foreach ($rules as $rule) {
            if (preg_match($rule['pattern'], $r)) {
                return [
                    'discipline'=>$rule['d'],
                    'domain'=>$rule['m'],
                    'subdomain'=>$rule['s'],
                    'family'=>$rule['f'],
                    'score'=>$rule['score'],
                ];
            }
        }

        return null;
    }

    private function markGroup(Collection $documents, array $result): void
    {
        foreach ($documents as $document) {
            if ($document->classification_status === 'VALIDATED') {
                continue;
            }

            $document->forceFill([
                'consensus_status'=>$result['status'],
                'consensus_score'=>$result['score'],
                'consensus_reason'=>$result['reason'],
                'consensus_at'=>now(),
                'classification_status'=>$result['status'] === 'CONFLICT'
                    ? 'TO_REVIEW'
                    : $document->classification_status,
            ])->save();
        }
    }

    private function result(
        string $status,
        float $score,
        ?string $discipline,
        ?string $domain,
        ?string $subdomain,
        string $reason
    ): array {
        return compact(
            'status','score','discipline','domain','subdomain','reason'
        );
    }
}
