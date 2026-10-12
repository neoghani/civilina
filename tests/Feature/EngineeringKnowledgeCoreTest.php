<?php

namespace Tests\Feature;

use App\Enums\ProjectStandardProfileStatus;
use App\Enums\RequirementApplicability;
use App\Enums\RequirementValidationStatus;
use App\Enums\StandardEditionStatus;
use App\Models\User;
use App\Services\Engineering\EngineeringKnowledgeService;
use App\Services\Engineering\EngineeringProjectService;
use App\Services\Organizations\OrganizationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineeringKnowledgeCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_document_is_owned_by_organization(): void
    {
        [$owner, $organization, $knowledge] =
            $this->knowledgeContext();

        $document =
            $knowledge->registerDocument(
                $organization,
                'EN-1992-1-1',
                'Concrete structures',
                'CEN',
                'EU',
                'structure',
                'standard',
                'en'
            );

        $this->assertSame(
            $organization->id,
            $document->organization_id
        );

        $this->assertSame(
            'EN-1992-1-1',
            $document->code
        );
    }

    public function test_standard_edition_and_clause_keep_source_traceability(): void
    {
        [$owner, $organization, $knowledge] =
            $this->knowledgeContext();

        $document =
            $knowledge->registerDocument(
                $organization,
                'STD-001',
                'Concrete standard'
            );

        $edition =
            $knowledge->registerEdition(
                $document,
                '2025',
                '2025-01-15',
                StandardEditionStatus::Active
            );

        $section =
            $knowledge->createSection(
                $edition,
                '7.3.1',
                'Concrete placement',
                null,
                84,
                86,
                'STD-001/2025#7.3.1',
                'sha256:abc'
            );

        $this->assertSame(
            StandardEditionStatus::Active,
            $edition->status
        );

        $this->assertSame(
            84,
            $section->page_start
        );

        $this->assertSame(
            'STD-001/2025#7.3.1',
            $section->source_locator
        );
    }

    public function test_clause_parent_must_belong_to_same_edition(): void
    {
        [$owner, $organization, $knowledge] =
            $this->knowledgeContext();

        $document =
            $knowledge->registerDocument(
                $organization,
                'STD-001',
                'Standard'
            );

        $editionA =
            $knowledge->registerEdition(
                $document,
                '2024'
            );

        $editionB =
            $knowledge->registerEdition(
                $document,
                '2025'
            );

        $parent =
            $knowledge->createSection(
                $editionA,
                '7',
                'Execution'
            );

        $this->expectException(
            DomainException::class
        );

        $knowledge->createSection(
            $editionB,
            '7.1',
            'Preparation',
            $parent
        );
    }

    public function test_agent_extraction_creates_candidate_not_operational_rule(): void
    {
        [$owner, $organization, $knowledge] =
            $this->knowledgeContext();

        [$edition, $section] =
            $this->standardWithSection(
                $organization,
                $knowledge
            );

        $requirement =
            $knowledge->captureRequirementCandidate(
                $section,
                'REQ-7.3.1-01',
                'acceptance_criterion',
                'Candidate acceptance criterion',
                'Interpretation produced by normative agent.',
                'Source excerpt retained for traceability.',
                'STD-001/2025#7.3.1',
                84,
                [
                    'origin' => 'normative_knowledge_agent',
                    'confidence' => 0.91,
                ]
            );

        $this->assertSame(
            RequirementValidationStatus::Candidate,
            $requirement->validation_status
        );

        $this->assertNull(
            $requirement->validated_by_user_id
        );

        $this->assertSame(
            'normative_knowledge_agent',
            $requirement->metadata['origin']
        );
    }

    public function test_human_member_validates_normative_requirement(): void
    {
        [$owner, $organization, $knowledge] =
            $this->knowledgeContext();

        [$edition, $section] =
            $this->standardWithSection(
                $organization,
                $knowledge
            );

        $requirement =
            $knowledge->captureRequirementCandidate(
                $section,
                'REQ-001',
                'inspection',
                'Inspection before concrete placement'
            );

        $validated =
            $knowledge->validateRequirement(
                $requirement,
                $owner,
                'Validated by engineer.'
            );

        $this->assertSame(
            RequirementValidationStatus::Validated,
            $validated->validation_status
        );

        $this->assertSame(
            $owner->id,
            $validated->validated_by_user_id
        );

        $this->assertNotNull(
            $validated->validated_at
        );
    }

    public function test_outsider_cannot_validate_normative_requirement(): void
    {
        [$owner, $organization, $knowledge] =
            $this->knowledgeContext();

        [$edition, $section] =
            $this->standardWithSection(
                $organization,
                $knowledge
            );

        $requirement =
            $knowledge->captureRequirementCandidate(
                $section,
                'REQ-001',
                'inspection',
                'Inspection requirement'
            );

        $outsider =
            User::factory()->create();

        $this->expectException(
            DomainException::class
        );

        $knowledge->validateRequirement(
            $requirement,
            $outsider
        );
    }

    public function test_project_standard_profile_rejects_foreign_organization_standard(): void
    {
        [$ownerA, $organizationA, $knowledge] =
            $this->knowledgeContext();

        $ownerB =
            User::factory()->create();

        $organizationB =
            app(OrganizationService::class)
                ->createForOwner(
                    $ownerB,
                    'Org B'
                );

        $project =
            app(EngineeringProjectService::class)
                ->createProject(
                    $organizationA,
                    'P-001',
                    'Project'
                );

        $profile =
            $knowledge->createProjectProfile(
                $project,
                'Project standards',
                $ownerA
            );

        [$foreignEdition] =
            $this->standardWithSection(
                $organizationB,
                $knowledge
            );

        $this->expectException(
            DomainException::class
        );

        $knowledge->addStandardToProfile(
            $profile,
            $foreignEdition,
            $ownerA
        );
    }

    public function test_validated_requirement_becomes_applicable_only_after_standard_is_selected(): void
    {
        [$owner, $organization, $knowledge] =
            $this->knowledgeContext();

        $project =
            app(EngineeringProjectService::class)
                ->createProject(
                    $organization,
                    'P-001',
                    'Project'
                );

        [$edition, $section] =
            $this->standardWithSection(
                $organization,
                $knowledge
            );

        $requirement =
            $knowledge->captureRequirementCandidate(
                $section,
                'REQ-001',
                'inspection',
                'Inspection before concrete placement'
            );

        $requirement =
            $knowledge->validateRequirement(
                $requirement,
                $owner
            );

        $profile =
            $knowledge->createProjectProfile(
                $project,
                'Project standards',
                $owner
            );

        $this->expectException(
            DomainException::class
        );

        $knowledge->decideRequirementApplicability(
            $profile,
            $requirement,
            RequirementApplicability::Applicable,
            $owner,
            'Applicable to reinforced concrete execution.'
        );
    }

    public function test_validated_requirement_can_be_activated_for_project_with_rationale(): void
    {
        [$owner, $organization, $knowledge] =
            $this->knowledgeContext();

        $project =
            app(EngineeringProjectService::class)
                ->createProject(
                    $organization,
                    'P-001',
                    'Project'
                );

        [$edition, $section] =
            $this->standardWithSection(
                $organization,
                $knowledge
            );

        $requirement =
            $knowledge->captureRequirementCandidate(
                $section,
                'REQ-001',
                'inspection',
                'Inspection before concrete placement'
            );

        $requirement =
            $knowledge->validateRequirement(
                $requirement,
                $owner
            );

        $profile =
            $knowledge->createProjectProfile(
                $project,
                'Project standards',
                $owner
            );

        $knowledge->addStandardToProfile(
            $profile,
            $edition,
            $owner,
            'Concrete execution',
            10,
            true
        );

        $decision =
            $knowledge->decideRequirementApplicability(
                $profile,
                $requirement,
                RequirementApplicability::Applicable,
                $owner,
                'Contractual standard for concrete works.'
            );

        $this->assertSame(
            RequirementApplicability::Applicable,
            $decision->applicability
        );

        $this->assertSame(
            $owner->id,
            $decision->validated_by_user_id
        );
    }

    public function test_project_standard_profile_requires_review_before_approval(): void
    {
        [$owner, $organization, $knowledge] =
            $this->knowledgeContext();

        $project =
            app(EngineeringProjectService::class)
                ->createProject(
                    $organization,
                    'P-001',
                    'Project'
                );

        $profile =
            $knowledge->createProjectProfile(
                $project,
                'Project standards',
                $owner
            );

        $this->expectException(
            DomainException::class
        );

        $knowledge->approveProfile(
            $profile,
            $owner
        );
    }

    public function test_approved_project_standard_profile_is_immutable(): void
    {
        [$owner, $organization, $knowledge] =
            $this->knowledgeContext();

        $project =
            app(EngineeringProjectService::class)
                ->createProject(
                    $organization,
                    'P-001',
                    'Project'
                );

        [$edition] =
            $this->standardWithSection(
                $organization,
                $knowledge
            );

        $profile =
            $knowledge->createProjectProfile(
                $project,
                'Project standards',
                $owner
            );

        $knowledge->markProfileForReview(
            $profile
        );

        $approved =
            $knowledge->approveProfile(
                $profile->refresh(),
                $owner
            );

        $this->assertSame(
            ProjectStandardProfileStatus::Approved,
            $approved->status
        );

        $this->expectException(
            DomainException::class
        );

        $knowledge->addStandardToProfile(
            $approved,
            $edition,
            $owner
        );
    }

    private function knowledgeContext(): array
    {
        $owner =
            User::factory()->create();

        $organization =
            app(OrganizationService::class)
                ->createForOwner(
                    $owner,
                    'Civilina Engineering'
                );

        return [
            $owner,
            $organization,
            app(EngineeringKnowledgeService::class),
        ];
    }

    private function standardWithSection(
        $organization,
        EngineeringKnowledgeService $knowledge
    ): array {
        $suffix =
            (string) fake()->unique()->numberBetween(
                1000,
                999999
            );

        $document =
            $knowledge->registerDocument(
                $organization,
                'STD-'.$suffix,
                'Construction standard '.$suffix
            );

        $edition =
            $knowledge->registerEdition(
                $document,
                '2025',
                '2025-01-01',
                StandardEditionStatus::Active
            );

        $section =
            $knowledge->createSection(
                $edition,
                '7.3.1',
                'Execution and inspection',
                null,
                84,
                86,
                'STD-'.$suffix.'/2025#7.3.1'
            );

        return [
            $edition,
            $section,
        ];
    }
}
