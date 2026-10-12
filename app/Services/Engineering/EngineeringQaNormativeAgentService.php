<?php

namespace App\Services\Engineering;

use App\Models\EngineeringQaInspection;
use App\Models\EngineeringQaNormativeLink;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class EngineeringQaNormativeAgentService
{
    public function __construct(private readonly EngineeringQaNormativeMatcher $matcher)
    {
    }

    public function suggest(
        EngineeringQaInspection $inspection,
        Project $project,
        ?string $userId = null,
        int $limit = 5
    ): array {
        if (! class_exists(\App\Models\StandardSection::class)) {
            return [
                'created' => 0,
                'message' => 'App\\Models\\StandardSection est absent. La bibliothèque normative doit être indexée avant la recherche.',
            ];
        }

        $sectionModel = new \App\Models\StandardSection();
        $table = $sectionModel->getTable();
        if (! Schema::hasTable($table)) {
            return [
                'created' => 0,
                'message' => 'La table des sections normatives est absente.',
            ];
        }

        $columns = Schema::getColumnListing($table);
        $searchColumns = array_values(array_intersect(
            ['title', 'heading', 'name', 'code', 'number', 'reference', 'content', 'text', 'body', 'section_text', 'clause_text', 'summary'],
            $columns
        ));

        if ($searchColumns === []) {
            return [
                'created' => 0,
                'message' => 'Aucune colonne textuelle exploitable n’a été trouvée dans '.$table.'.',
            ];
        }

        $keywords = $this->matcher->keywords(
            (string) $inspection->work_type,
            (string) $inspection->title,
            $inspection->acceptance_criteria,
            $inspection->requirement_source
        );

        $queryTerms = array_slice($keywords, 0, 10);
        $query = \App\Models\StandardSection::query();

        if ($queryTerms !== []) {
            $query->where(function ($q) use ($queryTerms, $searchColumns): void {
                foreach ($queryTerms as $termIndex => $term) {
                    foreach ($searchColumns as $columnIndex => $column) {
                        if ($termIndex === 0 && $columnIndex === 0) {
                            $q->where($column, 'like', '%'.$term.'%');
                        } else {
                            $q->orWhere($column, 'like', '%'.$term.'%');
                        }
                    }
                }
            });
        }

        $sections = $query->limit(300)->get();
        $profileContext = $this->projectProfileContext($project);

        $ranked = $sections->map(function (Model $section) use ($searchColumns, $keywords, $profileContext): array {
            $snapshot = $this->snapshot($section, $searchColumns);
            $match = $this->matcher->score($snapshot['search_text'], $keywords);
            $projectMatch = $this->matchesProjectProfile($section, $profileContext);

            $score = (float) $match['score'];
            if ($projectMatch === true) {
                $score = min(100.0, $score + 15.0);
            }

            return [
                'section' => $section,
                'snapshot' => $snapshot,
                'score' => $score,
                'reason' => $match['reason'],
                'project_match' => $projectMatch === true,
            ];
        })
        ->filter(fn (array $row): bool => $row['score'] >= 8.0)
        ->sortByDesc(fn (array $row): float => ($row['project_match'] ? 1000 : 0) + $row['score'])
        ->take(max(1, min($limit, 10)))
        ->values();

        EngineeringQaNormativeLink::query()
            ->where('engineering_qa_inspection_id', $inspection->getKey())
            ->where('status', EngineeringQaNormativeLink::STATUS_SUGGESTED)
            ->delete();

        $created = [];
        foreach ($ranked as $row) {
            $section = $row['section'];
            $snapshot = $row['snapshot'];

            $created[] = EngineeringQaNormativeLink::create([
                'organization_id' => (string) $inspection->organization_id,
                'project_id' => (string) $inspection->project_id,
                'engineering_qa_inspection_id' => $inspection->getKey(),
                'standard_section_id' => (string) $section->getKey(),
                'standard_document_id' => $snapshot['standard_document_id'],
                'standard_edition_id' => $snapshot['standard_edition_id'],
                'source_label' => $snapshot['source_label'],
                'source_reference' => $snapshot['source_reference'],
                'clause_code' => $snapshot['clause_code'],
                'excerpt' => $snapshot['excerpt'],
                'confidence_score' => $row['score'],
                'match_reason' => $row['reason'],
                'project_profile_match' => $row['project_match'],
                'status' => EngineeringQaNormativeLink::STATUS_SUGGESTED,
                'suggested_by' => 'civilina_normative_agent_v1',
                'notes' => null,
            ]);
        }

        return [
            'created' => count($created),
            'message' => count($created).' référence(s) normative(s) proposée(s). Validation humaine requise.',
        ];
    }

    private function snapshot(Model $section, array $searchColumns): array
    {
        $attributes = $section->getAttributes();
        $parts = [];
        foreach ($searchColumns as $column) {
            if (isset($attributes[$column]) && trim((string) $attributes[$column]) !== '') {
                $parts[] = (string) $attributes[$column];
            }
        }

        $sourceLabel = $this->firstAttribute($attributes, ['title', 'heading', 'name']);
        $sourceReference = $this->firstAttribute($attributes, ['reference', 'code', 'number']);
        $clauseCode = $this->firstAttribute($attributes, ['clause', 'clause_code', 'section_number', 'number', 'code']);
        $documentId = $this->firstAttribute($attributes, ['standard_document_id', 'document_id']);
        $editionId = $this->firstAttribute($attributes, ['standard_edition_id', 'edition_id']);

        $rawText = trim(implode("\n", $parts));
        $excerpt = mb_substr($rawText, 0, 1200);

        return [
            'search_text' => $rawText,
            'source_label' => $sourceLabel ?: ('Section #'.$section->getKey()),
            'source_reference' => $sourceReference,
            'clause_code' => $clauseCode,
            'standard_document_id' => $documentId !== null ? (string) $documentId : null,
            'standard_edition_id' => $editionId !== null ? (string) $editionId : null,
            'excerpt' => $excerpt,
        ];
    }

    private function projectProfileContext(Project $project): array
    {
        if (! method_exists($project, 'standardProfiles')) {
            return [];
        }

        try {
            $profiles = $project->standardProfiles()->get();
        } catch (\Throwable) {
            return [];
        }

        $context = [];
        foreach ($profiles as $profile) {
            foreach ($profile->getAttributes() as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $normalized = mb_strtolower((string) $key);
                if (
                    str_contains($normalized, 'standard')
                    || str_contains($normalized, 'document')
                    || str_contains($normalized, 'edition')
                ) {
                    $context[$normalized][(string) $value] = true;
                }
            }
        }

        return $context;
    }

    private function matchesProjectProfile(Model $section, array $context): ?bool
    {
        if ($context === []) {
            return null;
        }

        foreach ($section->getAttributes() as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $normalized = mb_strtolower((string) $key);
            if (
                ! str_contains($normalized, 'standard')
                && ! str_contains($normalized, 'document')
                && ! str_contains($normalized, 'edition')
            ) {
                continue;
            }

            foreach ($context as $profileKey => $values) {
                $sameFamily =
                    (str_contains($normalized, 'document') && str_contains($profileKey, 'document'))
                    || (str_contains($normalized, 'edition') && str_contains($profileKey, 'edition'))
                    || (str_contains($normalized, 'standard') && str_contains($profileKey, 'standard'));

                if ($sameFamily && isset($values[(string) $value])) {
                    return true;
                }
            }
        }

        return false;
    }

    private function firstAttribute(array $attributes, array $candidates): mixed
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $attributes) && $attributes[$candidate] !== null && $attributes[$candidate] !== '') {
                return $attributes[$candidate];
            }
        }

        return null;
    }
}
