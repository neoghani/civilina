<?php

namespace Tests\Unit;

use App\Services\Engineering\Normative\NormativeReferenceNormalizer;
use PHPUnit\Framework\TestCase;

class NormativeReferenceNormalizerTest extends TestCase
{
    public function test_detects_common_reference_codes(): void
    {
        $service = new NormativeReferenceNormalizer();

        $this->assertSame('NF EN 459-1', $service->detect('NF EN459-1 chaux.pdf')['code']);
        $this->assertSame('NF EN 459-1', $service->detect('NFEN459-1 chaux.pdf')['code']);
        $this->assertSame('EN 459-1', $service->detect('EN459-1 chaux.pdf')['code']);
        $this->assertSame('NF 12390-3', $service->detect('NF12390-3 2003.pdf')['code']);
        $this->assertSame('EN 206-1', $service->detect('EN206-1_DNA_EN206_06.pdf')['code']);
        $this->assertSame('GTR 1992', $service->detect('P11-300 GTR 1992.pdf')['code']);
        $this->assertSame('CFTR 11', $service->detect('CFTR 11.pdf')['code']);
        $this->assertSame('DT 787', $service->detect('DT787.pdf')['code']);
    }

    public function test_groups_national_adoption_into_same_family(): void
    {
        $service = new NormativeReferenceNormalizer();

        $nf = $service->detect('NF EN 459-1.pdf');
        $en = $service->detect('EN 459-1.pdf');

        $this->assertSame($nf['family'], $en['family']);
        $this->assertNotSame($nf['normalized'], $en['normalized']);
    }

    public function test_keeps_year_as_edition_information(): void
    {
        $service = new NormativeReferenceNormalizer();

        $result = $service->detect('NF12390-3 2003.pdf');

        $this->assertSame('NF 12390-3', $result['code']);
        $this->assertSame(2003, $result['year']);
        $this->assertSame('NF123903', $result['family']);
    }
}