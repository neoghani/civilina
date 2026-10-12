<?php

namespace App\Services\Engineering;

use App\Enums\DesignBasisCategory;
use App\Enums\EngineeringRevisionStatus;
use App\Models\DesignBasisItem;
use App\Models\DesignBasisRevision;
use App\Models\Project;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class DesignBasisService
{
    public function createRevision(
        Project $project,
        string $title,
        ?User $createdBy = null,
        ?string $changeSummary = null
    ): DesignBasisRevision {
        return DB::transaction(
            function () use (
                $project,
                $title,
                $createdBy,
                $changeSummary
            ): DesignBasisRevision {
                $nextRevision =
                    ((int) DesignBasisRevision::query()
                        ->where('project_id', $project->getKey())
                        ->max('revision_no')) + 1;

                return DesignBasisRevision::query()->create([
                    'project_id' => $project->getKey(),
                    'revision_no' => $nextRevision,
                    'status' => EngineeringRevisionStatus::Draft,
                    'title' => $this->requiredText($title),
                    'change_summary' => $this->optionalText($changeSummary),
                    'created_by_user_id' => $createdBy?->getKey(),
                ]);
            }
        );
    }

    public function addItem(
        DesignBasisRevision $revision,
        DesignBasisCategory $category,
        string $key,
        string $label,
        mixed $value,
        ?string $unit = null,
        ?string $sourceType = null,
        ?string $sourceReference = null,
        bool $isAssumption = false
    ): DesignBasisItem {
        if (
            $revision->status !==
            EngineeringRevisionStatus::Draft
        ) {
            throw new DomainException(
                'Only a draft Design Basis revision can be edited.'
            );
        }

        [
            $valueText,
            $valueNumber,
            $valueJson,
        ] = $this->normalizeValue($value);

        return DesignBasisItem::query()->create([
            'design_basis_revision_id' => $revision->getKey(),
            'category' => $category,
            'key' => $this->requiredText($key),
            'label' => $this->requiredText($label),
            'value_text' => $valueText,
            'value_number' => $valueNumber,
            'value_json' => $valueJson,
            'unit' => $this->optionalText($unit),
            'source_type' => $this->optionalText($sourceType),
            'source_reference' => $this->optionalText($sourceReference),
            'is_assumption' => $isAssumption,
        ]);
    }

    public function markForReview(
        DesignBasisRevision $revision
    ): DesignBasisRevision {
        if (
            $revision->status !==
            EngineeringRevisionStatus::Draft
        ) {
            throw new DomainException(
                'Only a draft Design Basis can move to review.'
            );
        }

        $revision->status =
            EngineeringRevisionStatus::Review;

        $revision->save();

        return $revision->refresh();
    }

    public function approve(
        DesignBasisRevision $revision
    ): DesignBasisRevision {
        if (
            $revision->status !==
            EngineeringRevisionStatus::Review
        ) {
            throw new DomainException(
                'Only a Design Basis in review can be approved.'
            );
        }

        $revision->status =
            EngineeringRevisionStatus::Approved;

        $revision->save();

        return $revision->refresh();
    }

    private function normalizeValue(mixed $value): array
    {
        if (is_array($value)) {
            return [null, null, $value];
        }

        if (is_int($value) || is_float($value)) {
            return [null, $value, null];
        }

        $text = trim((string) $value);

        if ($text === '') {
            throw new DomainException(
                'Design Basis item value cannot be empty.'
            );
        }

        return [$text, null, null];
    }

    private function requiredText(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new DomainException(
                'A non-empty value is required.'
            );
        }

        return $normalized;
    }

    private function optionalText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
