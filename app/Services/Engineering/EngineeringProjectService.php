<?php

namespace App\Services\Engineering;

use App\Enums\ActivityStatus;
use App\Enums\ProjectStatus;
use App\Enums\WorkPackageStatus;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectOuvrage;
use App\Models\ProjectZone;
use App\Models\WbsNode;
use App\Models\WorkPackage;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class EngineeringProjectService
{
    public function createProject(
        Organization $organization,
        string $code,
        string $name,
        ?string $description = null
    ): Project {
        return Project::query()->create([
            'organization_id' => $organization->getKey(),
            'code' => $this->code($code),
            'name' => $this->name($name),
            'description' => $this->optionalText($description),
            'status' => ProjectStatus::Draft,
        ]);
    }

    public function visibleProjects(
        Organization $organization
    ): Collection {
        return Project::query()
            ->forOrganization($organization)
            ->orderBy('code')
            ->get();
    }

    public function createWbsNode(
        Project $project,
        string $code,
        string $name,
        ?WbsNode $parent = null,
        ?string $description = null,
        int $sortOrder = 0
    ): WbsNode {
        if (
            $parent &&
            (int) $parent->project_id !==
                (int) $project->getKey()
        ) {
            throw new DomainException(
                'WBS parent must belong to the same project.'
            );
        }

        return WbsNode::query()->create([
            'project_id' => $project->getKey(),
            'parent_id' => $parent?->getKey(),
            'code' => $this->code($code),
            'name' => $this->name($name),
            'description' => $this->optionalText($description),
            'sort_order' => max(0, $sortOrder),
        ]);
    }

    public function createZone(
        Project $project,
        string $code,
        string $name,
        ?string $description = null
    ): ProjectZone {
        return ProjectZone::query()->create([
            'project_id' => $project->getKey(),
            'code' => $this->code($code),
            'name' => $this->name($name),
            'description' => $this->optionalText($description),
        ]);
    }

    public function createOuvrage(
        Project $project,
        string $code,
        string $name,
        ?string $description = null
    ): ProjectOuvrage {
        return ProjectOuvrage::query()->create([
            'project_id' => $project->getKey(),
            'code' => $this->code($code),
            'name' => $this->name($name),
            'description' => $this->optionalText($description),
        ]);
    }

    public function createWorkPackage(
        Project $project,
        WbsNode $wbsNode,
        string $code,
        string $name,
        ?ProjectZone $zone = null,
        ?ProjectOuvrage $ouvrage = null,
        ?string $description = null,
        WorkPackageStatus $status = WorkPackageStatus::Draft,
        int $sortOrder = 0
    ): WorkPackage {
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

        return WorkPackage::query()->create([
            'project_id' => $project->getKey(),
            'wbs_node_id' => $wbsNode->getKey(),
            'zone_id' => $zone?->getKey(),
            'ouvrage_id' => $ouvrage?->getKey(),
            'code' => $this->code($code),
            'name' => $this->name($name),
            'description' => $this->optionalText($description),
            'status' => $status,
            'sort_order' => max(0, $sortOrder),
        ]);
    }

    public function createActivity(
        Project $project,
        WorkPackage $workPackage,
        string $code,
        string $name,
        ?ProjectZone $zone = null,
        ?ProjectOuvrage $ouvrage = null,
        ?string $description = null,
        ?string $activityType = null,
        ActivityStatus $status = ActivityStatus::Planned,
        int $sortOrder = 0
    ): Activity {
        $this->assertSameProject(
            $project,
            $workPackage,
            'Work package'
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

        return Activity::query()->create([
            'project_id' => $project->getKey(),
            'work_package_id' => $workPackage->getKey(),
            'zone_id' => $zone?->getKey()
                ?? $workPackage->zone_id,
            'ouvrage_id' => $ouvrage?->getKey()
                ?? $workPackage->ouvrage_id,
            'code' => $this->code($code),
            'name' => $this->name($name),
            'description' => $this->optionalText($description),
            'activity_type' => $this->optionalText($activityType),
            'status' => $status,
            'sort_order' => max(0, $sortOrder),
        ]);
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

    private function name(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new DomainException(
                'A non-empty name is required.'
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
