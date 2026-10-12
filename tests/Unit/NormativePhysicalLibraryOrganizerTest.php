<?php

namespace Tests\Unit;

use App\Models\NormativeLibrarianDocument;
use App\Services\Engineering\NormativeLibrarian\NormativePhysicalLibraryOrganizer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class NormativePhysicalLibraryOrganizerTest extends TestCase
{
    private function bucket(array $attrs): array
    {
        $service = new NormativePhysicalLibraryOrganizer();
        $ref = new ReflectionClass($service);
        $method = $ref->getMethod('bucket');
        $method->setAccessible(true);

        return $method->invoke($service, new NormativeLibrarianDocument($attrs));
    }

    public function test_validated_goes_to_classified(): void
    {
        [$bucket] = $this->bucket([
            'classification_status'=>'VALIDATED',
            'extraction_status'=>'extracted',
        ]);

        $this->assertSame('01_NORMES_CLASSEES', $bucket);
    }

    public function test_safe_auto_goes_to_classified(): void
    {
        [$bucket] = $this->bucket([
            'classification_status'=>'AUTO_CLASSIFIED',
            'audit_status'=>'SAFE',
            'extraction_status'=>'extracted',
        ]);

        $this->assertSame('01_NORMES_CLASSEES', $bucket);
    }

    public function test_non_audited_auto_is_not_final(): void
    {
        [$bucket] = $this->bucket([
            'classification_status'=>'AUTO_CLASSIFIED',
            'audit_status'=>null,
            'extraction_status'=>'extracted',
        ]);

        $this->assertSame('02_AUTO_A_AUDITER', $bucket);
    }

    public function test_ocr_has_own_queue(): void
    {
        [$bucket] = $this->bucket([
            'classification_status'=>'UNCLASSIFIED',
            'extraction_status'=>'needs_ocr',
        ]);

        $this->assertSame('91_OCR_REQUIS', $bucket);
    }

    public function test_review_has_review_queue(): void
    {
        [$bucket] = $this->bucket([
            'classification_status'=>'TO_REVIEW',
            'extraction_status'=>'extracted',
        ]);

        $this->assertSame('90_A_VALIDER', $bucket);
    }
}
