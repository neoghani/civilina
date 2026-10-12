<?php

namespace Tests\Feature;

use App\Services\Engineering\NormativeLibrarian\NormativeQaRegistryMatchReviewService;
use App\Services\Engineering\NormativeLibrarian\NormativeQaRegistryMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NormativeQaRegistryMatchingV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_revoked_requirement_is_never_matched(): void
    {
        $inspectionId = $this->createInspection();
        $this->createRegistryFixture('REVOKED', 'PRIMARY_VALIDATED', true);

        $stats = app(NormativeQaRegistryMatchingService::class)->run(1, true, $inspectionId);

        $this->assertSame(0, $stats['eligible_registry']);
        $this->assertSame(1, $stats['NO_MATCH']);
        $this->assertDatabaseHas('civilina_qa_normative_matches_v3', [
            'inspection_id' => $inspectionId,
            'result_status' => 'NO_MATCH',
            'registry_requirement_id' => null,
        ]);
    }

    public function test_active_requirement_from_non_validated_source_is_never_matched(): void
    {
        $inspectionId = $this->createInspection();
        $this->createRegistryFixture('ACTIVE', 'UNREVIEWED', true);

        $stats = app(NormativeQaRegistryMatchingService::class)->run(1, true, $inspectionId);

        $this->assertSame(0, $stats['eligible_registry']);
        $this->assertSame(1, $stats['NO_MATCH']);
    }

    public function test_active_validated_requirement_can_match_and_keeps_audit_fields(): void
    {
        $inspectionId = $this->createInspection();
        $registryId = $this->createRegistryFixture('ACTIVE', 'PRIMARY_VALIDATED', true);

        $stats = app(NormativeQaRegistryMatchingService::class)->run(1, true, $inspectionId);

        $this->assertSame(1, $stats['MATCH']);
        $match = DB::table('civilina_qa_normative_matches_v3')->first();
        $this->assertSame($inspectionId, (int) $match->inspection_id);
        $this->assertSame($registryId, (int) $match->registry_requirement_id);
        $this->assertGreaterThanOrEqual(70, (float) $match->score);
        $this->assertNotEmpty($match->justification);
        $this->assertStringContainsString('provenance_hash', $match->provenance);
    }

    public function test_proposed_result_requires_and_records_human_review(): void
    {
        $inspectionId = $this->createInspection();
        $this->createRegistryFixture('ACTIVE', 'PRIMARY_VALIDATED', false);

        $stats = app(NormativeQaRegistryMatchingService::class)->run(1, true, $inspectionId);
        $this->assertSame(1, $stats['PROPOSED']);

        $match = DB::table('civilina_qa_normative_matches_v3')->first();
        $this->assertSame('HUMAN_REVIEW_REQUIRED', $match->review_status);

        app(NormativeQaRegistryMatchReviewService::class)->review(
            $match->id,
            'VALIDATED',
            'Concordance vérifiée humainement.',
            'QA_TEST'
        );

        $this->assertDatabaseHas('civilina_qa_normative_matches_v3', [
            'id' => $match->id,
            'result_status' => 'PROPOSED',
            'review_status' => 'VALIDATED',
            'reviewed_by' => 'QA_TEST',
        ]);
        $this->assertDatabaseHas('civilina_qa_normative_match_review_events_v3', [
            'match_id' => $match->id,
            'decision' => 'VALIDATED',
        ]);
    }

    public function test_legacy_links_are_preserved_by_v3_run(): void
    {
        $inspectionId = $this->createInspection();
        DB::table('engineering_qa_normative_links')->insert([
            'organization_id' => '1',
            'project_id' => '1',
            'engineering_qa_inspection_id' => $inspectionId,
            'inspection_id' => $inspectionId,
            'confidence_score' => 42,
            'score' => 42,
            'reason' => 'Lien historique conservé.',
            'status' => 'proposed',
            'metadata' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stats = app(NormativeQaRegistryMatchingService::class)->run(1, true, $inspectionId);

        $this->assertSame(1, $stats['legacy_seen']);
        $this->assertSame(1, $stats['legacy_preserved']);
        $this->assertSame(1, DB::table('engineering_qa_normative_links')->count());
    }

    private function createInspection(): int
    {
        return DB::table('engineering_qa_inspections')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'organization_id' => '1',
            'project_id' => '1',
            'template_key' => 'QA-CONCRETE',
            'work_type' => 'concrete',
            'inspection_code' => 'QA-'.Str::random(8),
            'title' => 'Contrôle béton frais par essai slump',
            'record_type' => 'inspection',
            'scope_type' => 'project',
            'scope_label' => 'Ouvrage béton',
            'status' => 'planned',
            'authorization_status' => 'none',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createRegistryFixture(
        string $publicationState,
        string $sourceReviewStatus,
        bool $humanClassification
    ): int {
        $suffix = Str::random(8);
        $deepDocumentId = random_int(1000, 9000);
        $atomicId = DB::table('civilina_normative_atomic_statements')->insertGetId([
            'deep_document_id' => $deepDocumentId,
            'document_id' => $deepDocumentId + 1,
            'section_id' => $deepDocumentId + 2,
            'reference_code' => 'NF TEST '.$suffix,
            'edition_key' => '2026',
            'section_code' => '5.2',
            'sequence' => 1,
            'statement_type' => 'REQUIREMENT',
            'review_status' => 'VALIDATED',
            'statement_text' => 'Le béton frais doit être contrôlé par essai slump.',
            'has_explicit_limit' => false,
            'has_numeric_unit' => false,
            'looks_like_formula' => false,
            'numeric_entities' => '[]',
            'segmentation_confidence' => 98,
            'statement_hash' => hash('sha256', 'statement-'.$suffix),
            'source_hash' => hash('sha256', 'source-'.$suffix),
            'semantic_version' => 'SEMANTIC_V2',
            'source_authority' => 'PRIMARY_CANDIDATE',
            'publication_eligible' => true,
            'reviewed_at' => now(),
            'provenance_hash' => hash('sha256', 'provenance-'.$suffix),
            'extraction_state' => 'ACTIVE',
            'generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('civilina_normative_atomic_source_reviews')->insert([
            'deep_document_id' => $deepDocumentId,
            'document_id' => $deepDocumentId + 1,
            'reference_code' => 'NF TEST '.$suffix,
            'edition_key' => '2026',
            'detected_authority' => 'PRIMARY_CANDIDATE',
            'review_status' => $sourceReviewStatus,
            'review_note' => $sourceReviewStatus === 'PRIMARY_VALIDATED' ? 'Source test validée.' : null,
            'reviewed_at' => $sourceReviewStatus === 'PRIMARY_VALIDATED' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($humanClassification) {
            DB::table('civilina_normative_classifications')->insert([
                'target_type' => 'DOCUMENT',
                'target_id' => $deepDocumentId,
                'deep_document_id' => $deepDocumentId,
                'family' => 'Béton',
                'classification_status' => 'CLASSIFIED',
                'evidence' => '[]',
                'source_fields' => '{}',
                'reviewed_by_name' => 'QA_TEST',
                'review_note' => 'Famille confirmée.',
                'reviewed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return DB::table('civilina_normative_registry_requirements')->insertGetId([
            'source_candidate_id' => null,
            'source_atomic_statement_id' => $atomicId,
            'source_document_id' => $deepDocumentId + 1,
            'deep_document_id' => $deepDocumentId,
            'source_section_id' => $deepDocumentId + 2,
            'reference_code' => 'NF TEST '.$suffix,
            'edition_key' => '2026',
            'section_code' => '5.2',
            'heading' => 'Contrôle béton',
            'requirement_type' => 'REQUIREMENT',
            'requirement_text' => 'Le béton frais doit être contrôlé par essai slump.',
            'extraction_confidence' => 98,
            'human_validation_note' => 'Exigence test validée.',
            'human_validated_at' => now(),
            'publication_state' => $publicationState,
            'revision' => 1,
            'source_hash' => hash('sha256', 'registry-source-'.$suffix),
            'provenance_hash' => hash('sha256', 'registry-provenance-'.$suffix),
            'publication_actor' => 'QA_TEST',
            'published_at' => now(),
            'revoked_at' => $publicationState === 'REVOKED' ? now() : null,
            'revocation_reason' => $publicationState === 'REVOKED' ? 'Fixture révoquée.' : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
