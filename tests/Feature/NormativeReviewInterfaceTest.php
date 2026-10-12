<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Engineering\EngineeringProjectService;
use App\Services\Organizations\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NormativeReviewInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_owner_can_open_source_and_atomic_review_queues(): void
    {
        $context = $this->context();

        $this->actingAs($context['owner'])->get($context['source_url'])
            ->assertOk()
            ->assertSee('Revue de l’autorité des sources')
            ->assertSee('PRIMARY_CANDIDATE');

        $this->actingAs($context['owner'])->get($context['atomic_url'])
            ->assertOk()
            ->assertSee('Revue des statements normatifs')
            ->assertSee('Le controle doit etre documente.');
    }

    public function test_outsider_cannot_open_normative_review_queue(): void
    {
        $context = $this->context();

        $this->actingAs(User::factory()->create())
            ->get($context['source_url'])
            ->assertForbidden();
    }

    private function context(): array
    {
        $owner = User::factory()->create();
        $organization = app(OrganizationService::class)->createForOwner($owner, 'Civilina QA');
        $project = app(EngineeringProjectService::class)->createProject($organization, 'QA-001', 'Normative Gate');
        $now = now();

        DB::table('civilina_normative_deep_documents')->insert([
            'id' => 10,
            'document_id' => 20,
            'reference_code' => 'NF TEST 100',
            'edition_key' => '2025',
            'status' => 'SUCCESS',
            'source_kind' => 'LOCAL_PDF',
            'char_count' => 100,
            'section_count' => 1,
            'requirement_candidate_count' => 1,
            'tolerance_candidate_count' => 0,
            'raw_text' => 'Norme locale de test.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('civilina_normative_atomic_source_reviews')->insert([
            'deep_document_id' => 10,
            'document_id' => 20,
            'reference_code' => 'NF TEST 100',
            'edition_key' => '2025',
            'detected_authority' => 'PRIMARY_CANDIDATE',
            'detected_reason' => 'Signal local de source primaire.',
            'review_status' => 'UNREVIEWED',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('civilina_normative_atomic_statements')->insert([
            'deep_document_id' => 10,
            'document_id' => 20,
            'section_id' => 30,
            'reference_code' => 'NF TEST 100',
            'edition_key' => '2025',
            'section_code' => '4.1',
            'sequence' => 1,
            'statement_type' => 'REQUIREMENT',
            'review_status' => 'CANDIDATE',
            'statement_text' => 'Le controle doit etre documente.',
            'has_explicit_limit' => false,
            'has_numeric_unit' => false,
            'looks_like_formula' => false,
            'segmentation_confidence' => 95,
            'statement_hash' => str_repeat('b', 64),
            'source_hash' => str_repeat('a', 64),
            'source_authority' => 'PRIMARY_CANDIDATE',
            'publication_eligible' => false,
            'eligibility_reason' => 'Revue requise.',
            'extraction_state' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $base = '/engineering/'.$organization->getRouteKey().'/projects/'.$project->getRouteKey();

        return [
            'owner' => $owner,
            'source_url' => $base.'/normative-source-review',
            'atomic_url' => $base.'/normative-deep-review',
        ];
    }
}
