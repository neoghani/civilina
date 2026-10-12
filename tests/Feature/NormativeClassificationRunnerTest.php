<?php

namespace Tests\Feature;

use App\Services\Engineering\NormativeLibrarian\NormativeClassificationRunnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NormativeClassificationRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_copies_of_the_same_standard_are_not_independent_evidence(): void
    {
        $canonicalId = $this->createLibraryDocument('source-a', null);
        $copyId = $this->createLibraryDocument('source-b', $canonicalId);

        foreach ([$canonicalId, $copyId] as $documentId) {
            DB::table('civilina_normative_deep_documents')->insert([
                'document_id' => $documentId,
                'reference_code' => 'NF EN TEST',
                'edition_key' => 'NFENTEST__2026',
                'status' => 'SUCCESS',
                'raw_text' => 'Exigences pour le béton et les essais de béton.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $stats = app(NormativeClassificationRunnerService::class)->run(null, true);

        $this->assertSame(2, $stats['documents_seen']);
        $this->assertSame(1, $stats['canonical_groups_seen']);
        $this->assertSame(1, $stats['duplicates_collapsed']);
        $this->assertSame(1, $stats['processed']);
        $this->assertSame(1, DB::table('civilina_normative_classifications')->count());
    }

    private function createLibraryDocument(string $key, ?int $duplicateOf): int
    {
        return DB::table('civilina_normative_librarian_documents')->insertGetId([
            'source_key' => hash('sha256', $key),
            'source_path' => 'local/'.$key.'.pdf',
            'relative_path' => $key.'.pdf',
            'filename' => $key.'.pdf',
            'extension' => 'pdf',
            'duplicate_of_id' => $duplicateOf,
            'reference_code' => 'NF EN TEST',
            'title' => 'Béton et essais',
            'ics_codes' => '[]',
            'classification_status' => 'TO_REVIEW',
            'canonical_group_key' => 'NFENTEST',
            'canonical_document_id' => $duplicateOf ?: null,
            'edition_key' => 'NFENTEST__2026',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
