<?php

namespace Tests\Feature;

use App\Services\Engineering\NormativeLibrarian\NormativeControlledPublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NormativeAtomicPublicationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_identical_republication_is_skipped_and_revocation_keeps_history(): void
    {
        $statementId = $this->createEligibleStatement();
        $service = app(NormativeControlledPublicationService::class);

        $first = $service->publish(10, false, $statementId, true, 'QA_TEST');
        $second = $service->publish(10, false, $statementId, true, 'QA_TEST');

        $this->assertSame(1, $first['published']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(1, DB::table('civilina_normative_registry_requirements')->count());

        $registry = DB::table('civilina_normative_registry_requirements')->first();
        $service->revoke($registry->id, 'Test de conservation de l historique.', 'QA_TEST');

        $this->assertSame(1, DB::table('civilina_normative_registry_requirements')->count());
        $this->assertSame('REVOKED', DB::table('civilina_normative_registry_requirements')->value('publication_state'));
        $this->assertDatabaseHas('civilina_normative_publication_events', ['event_type' => 'REVOKE']);
        $this->assertDatabaseHas('civilina_normative_registry_revisions', [
            'registry_requirement_id' => $registry->id,
            'event_type' => 'REVOKE',
            'publication_state' => 'REVOKED',
        ]);

        DB::table('civilina_normative_atomic_statements')->where('id', $statementId)->update([
            'statement_text' => 'La tolerance modifiee doit rester inferieure a 550 mm.',
            'statement_hash' => str_repeat('d', 64),
            'provenance_hash' => str_repeat('e', 64),
        ]);
        $changed = $service->publish(10, false, $statementId, true, 'QA_TEST');

        $this->assertSame(1, $changed['republished']);
        $this->assertSame(1, DB::table('civilina_normative_registry_requirements')->count());
        $this->assertSame(2, (int) DB::table('civilina_normative_registry_requirements')->value('revision'));
        $this->assertSame(3, DB::table('civilina_normative_registry_revisions')->count());
    }

    private function createEligibleStatement(): int
    {
        $now = now();
        DB::table('civilina_normative_atomic_source_reviews')->insert([
            'deep_document_id' => 10,
            'document_id' => 20,
            'reference_code' => 'NF TEST 100',
            'edition_key' => '2025',
            'detected_authority' => 'PRIMARY_CANDIDATE',
            'detected_reason' => 'Fixture locale.',
            'review_status' => 'PRIMARY_VALIDATED',
            'review_note' => 'Source verifiee pour le test.',
            'reviewed_by_name' => 'QA_TEST',
            'reviewed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('civilina_normative_atomic_statements')->insertGetId([
            'deep_document_id' => 10,
            'document_id' => 20,
            'section_id' => 30,
            'reference_code' => 'NF TEST 100',
            'edition_key' => '2025',
            'section_code' => '5.2',
            'sequence' => 1,
            'statement_type' => 'TOLERANCE',
            'review_status' => 'VALIDATED',
            'statement_text' => 'La tolerance doit rester inferieure a 600 mm.',
            'obligation_marker' => 'doit',
            'has_explicit_limit' => true,
            'has_numeric_unit' => true,
            'looks_like_formula' => false,
            'numeric_entities' => '[{"operator":"<","value":"600","unit":"mm"}]',
            'segmentation_confidence' => 98.5,
            'statement_hash' => str_repeat('b', 64),
            'source_hash' => str_repeat('a', 64),
            'semantic_version' => 'SEMANTIC_V2',
            'source_authority' => 'PRIMARY_CANDIDATE',
            'source_authority_reason' => 'Fixture locale.',
            'publication_eligible' => true,
            'eligibility_reason' => 'Fixture eligible.',
            'review_note' => 'Statement verifie pour le test.',
            'reviewed_by_name' => 'QA_TEST',
            'reviewed_at' => $now,
            'provenance_hash' => str_repeat('c', 64),
            'generated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
