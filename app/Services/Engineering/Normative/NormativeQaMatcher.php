<?php

namespace App\Services\Engineering\Normative;

use App\Models\EngineeringNormativeRequirement;
use App\Models\EngineeringQaInspection;
use App\Models\EngineeringQaNormativeLink;
use Illuminate\Support\Str;

class NormativeQaMatcher
{
    public function __construct(private NormativeAgentPromptBuilder $promptBuilder)
    {
    }

    public function suggestForInspection(EngineeringQaInspection $inspection): int
    {
        $context = $this->inspectionContext($inspection);
        $domain = $this->domainFromContext($context, (string) ($inspection->work_type ?? ''));

        if (! $domain) {
            return 0;
        }

        $contextTokens = $this->tokens($context);

        $requirements = EngineeringNormativeRequirement::query()
            ->with(['section.edition.standard'])
            ->whereHas('section.edition.standard', function ($query) use ($domain) {
                $query->where('domain', $domain);
            })
            ->limit(2200)
            ->get();

        $ranked = [];

        foreach ($requirements as $requirement) {
            $section = $requirement->section;
            $edition = $section?->edition;
            $standard = $edition?->standard;

            if (! $standard) {
                continue;
            }

            $candidateText = implode(' ', array_filter([
                (string) $standard->canonical_code,
                (string) $standard->title,
                (string) $section?->section_code,
                (string) $section?->heading,
                (string) $requirement->subject,
                (string) $requirement->requirement_text,
                implode(' ', (array) $requirement->keywords),
            ]));

            if (! $this->candidateIsRelevant(
                $context,
                $candidateText,
                $domain,
                $requirement->subject
            )) {
                continue;
            }

            $candidateTokens = $this->tokenSet($candidateText);
            $hits = count(array_intersect($contextTokens, array_keys($candidateTokens)));
            $subjectMatch = $this->subjectMatches(
                $context,
                (string) $requirement->subject,
                $candidateText
            );

            /*
             * Règle V2.1.3 :
             * même domaine ≠ automatiquement pertinent.
             * Il faut une vraie concordance contrôle ↔ texte / sujet.
             */
            if ($hits === 0 && ! $subjectMatch) {
                continue;
            }

            $score = 34;
            $score += min(40, $hits * 8);

            if ($subjectMatch) {
                $score += 18;
            }

            if (in_array($requirement->requirement_type, ['tolerance', 'limit', 'acceptance'], true)) {
                $score += 6;
            }

            if ($section?->heading && $this->hasExactTokenOverlap($context, (string) $section->heading)) {
                $score += 6;
            }

            $score = min(100, $score);

            if ($score < 58) {
                continue;
            }

            $ranked[] = [$score, $requirement];
        }

        usort($ranked, fn ($a, $b) => $b[0] <=> $a[0]);
        $ranked = array_slice($ranked, 0, (int) config('civilina_normative.match_limit', 6));

        $count = 0;

        foreach ($ranked as [$score, $requirement]) {
            $section = $requirement->section;
            $edition = $section?->edition;
            $standard = $edition?->standard;

            if (! $standard) {
                continue;
            }

            $reason = sprintf(
                'Domaine=%s ; contrôle="%s" ; section="%s" ; exigence="%s".',
                $domain,
                mb_substr($context, 0, 180),
                mb_substr((string) ($section?->heading ?? $section?->section_code ?? ''), 0, 140),
                mb_substr((string) $requirement->requirement_text, 0, 240)
            );

            EngineeringQaNormativeLink::query()->updateOrCreate(
                [
                    'inspection_id' => $inspection->getKey(),
                    'requirement_id' => $requirement->id,
                ],
                [
                    'standard_id' => $standard->id,
                    'edition_id' => $edition?->id,
                    'section_id' => $section?->id,
                    'score' => $score,
                    'reason' => $reason,
                    'status' => 'proposed',
                    'metadata' => [
                        'agent_prompt_version' => 'CIVILINA-NORMATIVE-V2.1.3',
                        'work_type' => $inspection->work_type ?? null,
                        'semantic_guard' => true,
                    ],
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Filtre sémantique public pour tests.
     *
     * Il empêche par exemple :
     * "fond de terrassement" -> "élément de fondation ... bétonnage"
     * uniquement parce que "fond" est une sous-chaîne de "fondation".
     */
    public function candidateIsRelevant(
        string $inspectionContext,
        string $candidateText,
        string $domain,
        ?string $candidateSubject = null
    ): bool {
        $context = $this->normalize($inspectionContext);
        $candidate = $this->normalize($candidateText);

        $contextTokens = $this->tokens($context);
        $candidateTokenSet = $this->tokenSet($candidate);

        $exactHits = count(array_intersect($contextTokens, array_keys($candidateTokenSet)));
        $subjectMatch = $this->subjectMatches($context, (string) $candidateSubject, $candidate);

        if ($this->hasMaterialMismatch($context, $candidate, $domain)) {
            return false;
        }

        return $exactHits > 0 || $subjectMatch;
    }

    public function prompt(): string
    {
        return $this->promptBuilder->systemPrompt();
    }

    private function inspectionContext(EngineeringQaInspection $inspection): string
    {
        $attributes = $inspection->getAttributes();

        $fields = [
            'code',
            'title',
            'name',
            'description',
            'work_type',
            'record_type',
            'inspection_type',
            'required_record',
            'activity_name',
            'activity_label',
        ];

        $parts = [];

        foreach ($fields as $field) {
            if (! empty($attributes[$field])) {
                $parts[] = (string) $attributes[$field];
            }
        }

        /*
         * L'activité héritée n'est ajoutée que si elle n'est pas manifestement
         * incohérente avec le type de travaux.
         */
        try {
            if (method_exists($inspection, 'activity') && $inspection->activity) {
                $activity = (string) ($inspection->activity->name ?? $inspection->activity->title ?? '');

                if (! $this->activityLooksWrong((string) ($inspection->work_type ?? ''), $activity)) {
                    $parts[] = $activity;
                }
            }
        } catch (\Throwable) {
        }

        return trim(implode(' ', array_filter($parts)));
    }

    private function activityLooksWrong(string $workType, string $activity): bool
    {
        $work = $this->normalize($workType);
        $activity = $this->normalize($activity);

        if (
            (str_contains($work, 'earthwork') || str_contains($work, 'road') || str_contains($work, 'terrasse'))
            && preg_match('/\b(ferraillage|coffrage|beton|poteau)\b/u', $activity)
        ) {
            return true;
        }

        return false;
    }

    private function domainFromContext(string $context, string $workType): ?string
    {
        $text = $this->normalize($context.' '.$workType);

        if (
            preg_match('/\b(earthwork|road|terrassement|remblai|troxler|deflexion|portance|compactage|chaussee)\b/u', $text)
        ) {
            return 'earthworks_road';
        }

        if (preg_match('/\b(soudage|soudure|welding)\b/u', $text)) {
            return 'welding';
        }

        if (
            preg_match('/\b(steel|metal|metallique|charpente|serrage|boulon|poutre|solive|lierne|contreventement)\b/u', $text)
        ) {
            return 'steel';
        }

        if (
            preg_match('/\b(concrete|beton|ferraillage|coffrage|armature|eprouvette)\b/u', $text)
        ) {
            return 'concrete';
        }

        return null;
    }

    private function subjectMatches(string $context, string $subject, string $candidateText): bool
    {
        $context = $this->normalize($context);
        $subject = $this->normalize($subject);
        $candidate = $this->normalize($candidateText);

        $groups = [
            'compacite' => ['troxler', 'compacite', 'compactage', 'densite', 'masse volumique', 'proctor'],
            'portance' => ['plaque', 'portance', 'ev1', 'ev2', 'module'],
            'deflexion' => ['deflexion'],
            'sol_terrassement' => ['sol naturel', 'terrassement', 'remblai', 'plateforme', 'couche', 'fond de terrassement'],
            'ferraillage' => ['ferraillage', 'armature', 'enrobage', 'recouvrement', 'ancrage'],
            'coffrage' => ['coffrage', 'dimension', 'alignement', 'niveau', 'aplomb'],
            'beton_frais' => ['beton frais', 'affaissement', 'slump', 'consistance', 'temperature', 'prelevement'],
            'resistance_beton' => ['eprouvette', 'compression', 'resistance', '7 jours', '28 jours'],
            'serrage' => ['serrage', 'couple', 'boulon', 'precontrainte', 'dynamometrique'],
            'montage_acier' => ['montage', 'charpente', 'alignement', 'aplomb', 'verticalite'],
        ];

        foreach ($groups as $terms) {
            $contextHasGroup = false;
            $candidateHasGroup = false;

            foreach ($terms as $term) {
                $needle = $this->normalize($term);

                if (str_contains($context, $needle) || str_contains($subject, $needle)) {
                    $contextHasGroup = true;
                }

                if (str_contains($candidate, $needle) || str_contains($subject, $needle)) {
                    $candidateHasGroup = true;
                }
            }

            if ($contextHasGroup && $candidateHasGroup) {
                return true;
            }
        }

        return false;
    }

    private function hasMaterialMismatch(string $context, string $candidate, string $domain): bool
    {
        if ($domain !== 'earthworks_road') {
            return false;
        }

        $earthworkControl = preg_match(
            '/\b(troxler|compacite|compactage|sol|remblai|terrassement|plaque|portance|deflexion|chaussee|plateforme)\b/u',
            $context
        );

        if (! $earthworkControl) {
            return false;
        }

        $candidateHasConcreteStructure = preg_match(
            '/\b(betonnage|armature|ferraillage|coffrage|eprouvette|fondation en beton)\b/u',
            $candidate
        );

        $candidateHasEarthwork = preg_match(
            '/\b(sol|remblai|terrassement|compactage|compacite|portance|deflexion|plaque|chaussee|plateforme|couche)\b/u',
            $candidate
        );

        return (bool) ($candidateHasConcreteStructure && ! $candidateHasEarthwork);
    }

    private function hasExactTokenOverlap(string $left, string $right): bool
    {
        $leftTokens = $this->tokens($left);
        $rightSet = $this->tokenSet($right);

        return count(array_intersect($leftTokens, array_keys($rightSet))) > 0;
    }

    /**
     * @return array<int,string>
     */
    private function tokens(string $text): array
    {
        $normalized = $this->normalize($text);
        $tokens = preg_split('/[^a-z0-9]+/', $normalized) ?: [];

        $stop = [
            'avec', 'dans', 'pour', 'sans', 'controle', 'inspection', 'reception',
            'proces', 'verbal', 'essai', 'mesure', 'work', 'type', 'record',
            'required', 'des', 'une', 'les', 'sur', 'par', 'naturelle',
        ];

        $tokens = array_map(fn ($token) => $this->normalizeToken($token), $tokens);

        return array_values(array_unique(array_filter(
            $tokens,
            fn ($token) => strlen($token) >= 3 && ! in_array($token, $stop, true)
        )));
    }

    /**
     * @return array<string,bool>
     */
    private function tokenSet(string $text): array
    {
        $set = [];

        foreach ($this->tokens($text) as $token) {
            $set[$token] = true;
        }

        return $set;
    }

    private function normalizeToken(string $token): string
    {
        $token = trim($token);

        if (strlen($token) > 5 && str_ends_with($token, 's')) {
            $token = substr($token, 0, -1);
        }

        return $token;
    }

    private function normalize(string $value): string
    {
        $value = Str::lower(Str::ascii($value));
        $value = str_replace(['_', '/', '\\', '-'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }
}
