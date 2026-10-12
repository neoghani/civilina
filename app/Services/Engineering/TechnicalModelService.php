<?php

namespace App\Services\Engineering;

use App\Enums\EngineeringRevisionStatus;
use App\Models\Activity;
use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectOuvrage;
use App\Models\ProjectZone;
use App\Models\TechnicalObject;
use App\Models\TechnicalObjectRevision;
use App\Models\User;
use App\Models\WbsNode;
use App\Models\WorkPackage;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TechnicalModelService
{
    public function createMaterial(
        Project $project,
        string $code,
        string $name,
        string $materialType,
        ?string $standardReference = null,
        array $properties = []
    ): Material {
        return Material::query()->create([
            'project_id' => $project->getKey(),
            'code' => $this->code($code),
            'name' => $this->requiredText($name),
            'material_type' => $this->requiredText($materialType),
            'standard_reference' => $this->optionalText($standardReference),
            'properties' => $properties,
        ]);
    }

    public function createObject(
        Project $project,
        string $code,
        string $name,
        string $objectType,
        string $discipline,
        ?TechnicalObject $parent = null,
        ?WbsNode $wbsNode = null,
        ?ProjectZone $zone = null,
        ?ProjectOuvrage $ouvrage = null,
        ?WorkPackage $workPackage = null,
        ?Activity $activity = null
    ): TechnicalObject {
        $this->assertSameProject(
            $project,
            $parent,
            'Technical object parent'
        );

        $this->assertSameProject(
            $project,
            $wbsNode,
            'WBS node'
        );

        $this->assertSameProject(
            $project,
            $zone,
            'Zone'
        );

        $this->assertSameProject(
            $project,
            $ouvrage,
            'Ouvrage'
        );

        $this->assertSameProject(
            $project,
            $workPackage,
            'Work package'
        );

        $this->assertSameProject(
            $project,
            $activity,
            'Activity'
        );

        if ($activity !== null) {
            if (
                $workPackage !== null &&
                (int) $activity->work_package_id !==
                    (int) $workPackage->getKey()
            ) {
                throw new DomainException(
                    'Activity must belong to the selected work package.'
                );
            }

            $workPackage ??= $activity->workPackage;
        }

        if ($workPackage !== null) {
            if (
                $wbsNode !== null &&
                (int) $workPackage->wbs_node_id !==
                    (int) $wbsNode->getKey()
            ) {
                throw new DomainException(
                    'Work package must belong to the selected WBS node.'
                );
            }

            $wbsNode ??= $workPackage->wbsNode;
        }

        return TechnicalObject::query()->create([
            'project_id' => $project->getKey(),
            'parent_id' => $parent?->getKey(),
            'wbs_node_id' => $wbsNode?->getKey(),
            'work_package_id' => $workPackage?->getKey(),
            'activity_id' => $activity?->getKey(),
            'zone_id' => $zone?->getKey(),
            'ouvrage_id' => $ouvrage?->getKey(),
            'code' => $this->code($code),
            'name' => $this->requiredText($name),
            'object_type' => $this->requiredText($objectType),
            'discipline' => $this->requiredText($discipline),
        ]);
    }

    public function createRevision(
        TechnicalObject $technicalObject,
        array $geometry,
        array $properties = [],
        array $assumptions = [],
        ?Material $material = null,
        ?User $createdBy = null,
        array $provenance = [],
        ?string $changeSummary = null
    ): TechnicalObjectRevision {
        if ($geometry === []) {
            throw new DomainException(
                'Technical object geometry cannot be empty.'
            );
        }

        if (
            $material &&
            (int) $material->project_id !==
                (int) $technicalObject->project_id
        ) {
            throw new DomainException(
                'Material must belong to the same project as the technical object.'
            );
        }

        return DB::transaction(
            function () use (
                $technicalObject,
                $geometry,
                $properties,
                $assumptions,
                $material,
                $createdBy,
                $provenance,
                $changeSummary
            ): TechnicalObjectRevision {
                $nextRevision =
                    ((int) TechnicalObjectRevision::query()
                        ->where(
                            'technical_object_id',
                            $technicalObject->getKey()
                        )
                        ->max('revision_no')) + 1;

                return TechnicalObjectRevision::query()->create([
                    'technical_object_id' => $technicalObject->getKey(),
                    'revision_no' => $nextRevision,
                    'status' => EngineeringRevisionStatus::Draft,
                    'material_id' => $material?->getKey(),
                    'geometry' => $geometry,
                    'properties' => $properties,
                    'assumptions' => $assumptions,
                    'provenance' => $provenance,
                    'change_summary' => $this->optionalText($changeSummary),
                    'created_by_user_id' => $createdBy?->getKey(),
                ]);
            }
        );
    }

    public function markForReview(
        TechnicalObjectRevision $revision
    ): TechnicalObjectRevision {
        if (
            $revision->status !==
            EngineeringRevisionStatus::Draft
        ) {
            throw new DomainException(
                'Only a draft technical revision can move to review.'
            );
        }

        $revision->status =
            EngineeringRevisionStatus::Review;

        $revision->save();

        return $revision->refresh();
    }

    public function approve(
        TechnicalObjectRevision $revision
    ): TechnicalObjectRevision {
        if (
            $revision->status !==
            EngineeringRevisionStatus::Review
        ) {
            throw new DomainException(
                'Only a technical revision in review can be approved.'
            );
        }

        $revision->status =
            EngineeringRevisionStatus::Approved;

        $revision->save();

        return $revision->refresh();
    }

    private function assertSameProject(
        Project $project,
        ?object $model,
        string $label
    ): void {
        if ($model === null) {
            return;
        }

        if (
            ! isset($model->project_id) ||
            (int) $model->project_id !==
                (int) $project->getKey()
        ) {
            throw new DomainException(
                $label.' must belong to the same project.'
            );
        }
    }

    private function code(string $value): string
    {
        $normalized = Str::upper(trim($value));

        if ($normalized === '') {
            throw new DomainException(
                'A non-empty code is required.'
            );
        }

        return $normalized;
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
