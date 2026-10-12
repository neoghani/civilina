<?php

namespace App\Services\Engineering;

use App\Enums\ProjectStandardProfileStatus;
use App\Enums\RequirementApplicability;
use App\Enums\RequirementValidationStatus;
use App\Enums\StandardEditionStatus;
use App\Models\NormativeRequirement;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\ProjectRequirementDecision;
use App\Models\ProjectStandard;
use App\Models\ProjectStandardProfile;
use App\Models\RequirementRelation;
use App\Models\StandardDocument;
use App\Models\StandardEdition;
use App\Models\StandardSection;
use App\Models\User;
use DomainException;
use Illuminate\Support\Str;

class EngineeringKnowledgeService
{
    public function registerDocument(
        Organization $organization,
        string $code,
        string $title,
        ?string $publisher = null,
        ?string $jurisdiction = null,
        ?string $discipline = null,
        ?string $documentType = null,
        ?string $language = null,
        ?string $sourceFilename = null,
        ?string $sourceChecksum = null,
        array $metadata = []
    ): StandardDocument {
        return StandardDocument::query()->create([
            'organization_id' => $organization->getKey(),
            'code' => $this->code($code),
            'title' => $this->requiredText($title),
            'publisher' => $this->optionalText($publisher),
            'jurisdiction' => $this->optionalText($jurisdiction),
            'discipline' => $this->optionalText($discipline),
            'document_type' => $this->optionalText($documentType),
            'language' => $this->optionalText($language),
            'source_filename' => $this->optionalText($sourceFilename),
            'source_checksum' => $this->optionalText($sourceChecksum),
            'metadata' => $metadata,
        ]);
    }

    public function registerEdition(
        StandardDocument $document,
        string $editionCode,
        ?string $publicationDate = null,
        StandardEditionStatus $status = StandardEditionStatus::Draft,
        array $metadata = []
    ): StandardEdition {
        return StandardEdition::query()->create([
            'standard_document_id' => $document->getKey(),
            'edition_code' => $this->requiredText($editionCode),
            'publication_date' => $publicationDate,
            'status' => $status,
            'metadata' => $metadata,
        ]);
    }

    public function createSection(
        StandardEdition $edition,
        string $clauseReference,
        string $title,
        ?StandardSection $parent = null,
        ?int $pageStart = null,
        ?int $pageEnd = null,
        ?string $sourceLocator = null,
        ?string $contentHash = null,
        int $sortOrder = 0,
        array $metadata = []
    ): StandardSection {
        if (
            $parent &&
            (int) $parent->standard_edition_id !==
                (int) $edition->getKey()
        ) {
            throw new DomainException(
                'Section parent must belong to the same standard edition.'
            );
        }

        if (
            $pageStart !== null &&
            $pageEnd !== null &&
            $pageEnd < $pageStart
        ) {
            throw new DomainException(
                'Section ending page cannot precede its starting page.'
            );
        }

        return StandardSection::query()->create([
            'standard_edition_id' => $edition->getKey(),
            'parent_id' => $parent?->getKey(),
            'clause_reference' => $this->requiredText($clauseReference),
            'title' => $this->requiredText($title),
            'page_start' => $pageStart,
            'page_end' => $pageEnd,
            'source_locator' => $this->optionalText($sourceLocator),
            'content_hash' => $this->optionalText($contentHash),
            'sort_order' => max(0, $sortOrder),
            'metadata' => $metadata,
        ]);
    }

    public function captureRequirementCandidate(
        StandardSection $section,
        string $requirementCode,
        string $requirementType,
        string $summary,
        ?string $interpretation = null,
        ?string $sourceExcerpt = null,
        ?string $sourceLocator = null,
        ?int $pageNumber = null,
        array $metadata = []
    ): NormativeRequirement {
        return NormativeRequirement::query()->create([
            'standard_section_id' => $section->getKey(),
            'requirement_code' => $this->requiredText($requirementCode),
            'requirement_type' => $this->requiredText($requirementType),
            'summary' => $this->requiredText($summary),
            'interpretation' => $this->optionalText($interpretation),
            'source_excerpt' => $this->optionalText($sourceExcerpt),
            'source_locator' => $this->optionalText($sourceLocator),
            'page_number' => $pageNumber,
            'validation_status' => RequirementValidationStatus::Candidate,
            'metadata' => $metadata,
        ]);
    }

    public function validateRequirement(
        NormativeRequirement $requirement,
        User $validator,
        ?string $validationNote = null
    ): NormativeRequirement {
        $this->assertActiveOrganizationMember(
            $validator,
            $this->organizationIdForRequirement($requirement)
        );

        $requirement->validation_status =
            RequirementValidationStatus::Validated;

        $requirement->validated_by_user_id =
            $validator->getKey();

        $requirement->validated_at = now();

        $requirement->validation_note =
            $this->optionalText($validationNote);

        $requirement->save();

        return $requirement->refresh();
    }

    public function rejectRequirement(
        NormativeRequirement $requirement,
        User $validator,
        string $validationNote
    ): NormativeRequirement {
        $this->assertActiveOrganizationMember(
            $validator,
            $this->organizationIdForRequirement($requirement)
        );

        $requirement->validation_status =
            RequirementValidationStatus::Rejected;

        $requirement->validated_by_user_id =
            $validator->getKey();

        $requirement->validated_at = now();

        $requirement->validation_note =
            $this->requiredText($validationNote);

        $requirement->save();

        return $requirement->refresh();
    }

    public function relateRequirements(
        NormativeRequirement $source,
        NormativeRequirement $target,
        string $relationType,
        ?string $notes = null
    ): RequirementRelation {
        if ($source->is($target)) {
            throw new DomainException(
                'A normative requirement cannot relate to itself.'
            );
        }

        if (
            $this->organizationIdForRequirement($source) !==
            $this->organizationIdForRequirement($target)
        ) {
            throw new DomainException(
                'Related normative requirements must belong to the same organization knowledge base.'
            );
        }

        return RequirementRelation::query()->create([
            'source_requirement_id' => $source->getKey(),
            'target_requirement_id' => $target->getKey(),
            'relation_type' => $this->requiredText($relationType),
            'notes' => $this->optionalText($notes),
        ]);
    }

    public function createProjectProfile(
        Project $project,
        string $name,
        ?User $createdBy = null,
        ?string $changeSummary = null
    ): ProjectStandardProfile {
        if ($createdBy) {
            $this->assertActiveOrganizationMember(
                $createdBy,
                $project->organization_id
            );
        }

        $nextRevision =
            ((int) ProjectStandardProfile::query()
                ->where('project_id', $project->getKey())
                ->max('revision_no')) + 1;

        return ProjectStandardProfile::query()->create([
            'project_id' => $project->getKey(),
            'revision_no' => $nextRevision,
            'name' => $this->requiredText($name),
            'status' => ProjectStandardProfileStatus::Draft,
            'change_summary' => $this->optionalText($changeSummary),
            'created_by_user_id' => $createdBy?->getKey(),
        ]);
    }

    public function addStandardToProfile(
        ProjectStandardProfile $profile,
        StandardEdition $edition,
        User $validator,
        ?string $scopeNotes = null,
        int $priority = 100,
        bool $isContractual = false
    ): ProjectStandard {
        $project = $profile->project;

        $editionOrganizationId =
            $this->organizationIdForEdition($edition);

        if (
            (int) $project->organization_id !==
            $editionOrganizationId
        ) {
            throw new DomainException(
                'Project can only use standards from its organization knowledge base.'
            );
        }

        $this->assertEditableProfile($profile);

        $this->assertActiveOrganizationMember(
            $validator,
            $project->organization_id
        );

        return ProjectStandard::query()->create([
            'project_standard_profile_id' => $profile->getKey(),
            'standard_edition_id' => $edition->getKey(),
            'scope_notes' => $this->optionalText($scopeNotes),
            'priority' => max(0, $priority),
            'is_contractual' => $isContractual,
            'validated_by_user_id' => $validator->getKey(),
            'validated_at' => now(),
        ]);
    }

    public function decideRequirementApplicability(
        ProjectStandardProfile $profile,
        NormativeRequirement $requirement,
        RequirementApplicability $applicability,
        User $validator,
        string $rationale
    ): ProjectRequirementDecision {
        if (
            $requirement->validation_status !==
            RequirementValidationStatus::Validated
        ) {
            throw new DomainException(
                'Only a human-validated normative requirement can become operational on a project.'
            );
        }

        $this->assertEditableProfile($profile);

        $this->assertActiveOrganizationMember(
            $validator,
            $profile->project->organization_id
        );

        $editionId =
            $this->editionIdForRequirement($requirement);

        $profileHasEdition =
            ProjectStandard::query()
                ->where(
                    'project_standard_profile_id',
                    $profile->getKey()
                )
                ->where(
                    'standard_edition_id',
                    $editionId
                )
                ->exists();

        if (! $profileHasEdition) {
            throw new DomainException(
                'Requirement edition must be selected in the project standard profile before applicability can be decided.'
            );
        }

        return ProjectRequirementDecision::query()->updateOrCreate(
            [
                'project_standard_profile_id' => $profile->getKey(),
                'normative_requirement_id' => $requirement->getKey(),
            ],
            [
                'applicability' => $applicability,
                'rationale' => $this->requiredText($rationale),
                'validated_by_user_id' => $validator->getKey(),
                'validated_at' => now(),
            ]
        );
    }

    public function markProfileForReview(
        ProjectStandardProfile $profile
    ): ProjectStandardProfile {
        $this->assertEditableProfile($profile);

        $profile->status =
            ProjectStandardProfileStatus::Review;

        $profile->save();

        return $profile->refresh();
    }

    public function approveProfile(
        ProjectStandardProfile $profile,
        User $validator
    ): ProjectStandardProfile {
        if (
            $profile->status !==
            ProjectStandardProfileStatus::Review
        ) {
            throw new DomainException(
                'Only a project standard profile in review can be approved.'
            );
        }

        $this->assertActiveOrganizationMember(
            $validator,
            $profile->project->organization_id
        );

        $profile->status =
            ProjectStandardProfileStatus::Approved;

        $profile->save();

        return $profile->refresh();
    }

    private function organizationIdForRequirement(
        NormativeRequirement $requirement
    ): int {
        $section = $this->sectionForRequirement($requirement);

        $edition = StandardEdition::query()->find(
            $section->standard_edition_id
        );

        if (! $edition) {
            throw new DomainException(
                'Normative requirement references a missing standard edition.'
            );
        }

        return $this->organizationIdForEdition($edition);
    }

    private function editionIdForRequirement(
        NormativeRequirement $requirement
    ): int {
        return (int) $this
            ->sectionForRequirement($requirement)
            ->standard_edition_id;
    }

    private function sectionForRequirement(
        NormativeRequirement $requirement
    ): StandardSection {
        $sectionId =
            (int) $requirement->standard_section_id;

        $section =
            StandardSection::query()->find($sectionId);

        if (! $section) {
            throw new DomainException(
                'Normative requirement references a missing standard section.'
            );
        }

        return $section;
    }

    private function organizationIdForEdition(
        StandardEdition $edition
    ): int {
        $document =
            StandardDocument::query()->find(
                $edition->standard_document_id
            );

        if (! $document) {
            throw new DomainException(
                'Standard edition references a missing standard document.'
            );
        }

        return (int) $document->organization_id;
    }

    private function assertEditableProfile(
        ProjectStandardProfile $profile
    ): void {
        if (
            $profile->status !==
            ProjectStandardProfileStatus::Draft
        ) {
            throw new DomainException(
                'Only a draft project standard profile can be edited.'
            );
        }
    }

    private function assertActiveOrganizationMember(
        User $user,
        int $organizationId
    ): void {
        $isActive =
            OrganizationMembership::query()
                ->where('organization_id', $organizationId)
                ->where('user_id', $user->getKey())
                ->where('status', 'active')
                ->exists();

        if (! $isActive) {
            throw new DomainException(
                'Validator must be an active member of the organization.'
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
