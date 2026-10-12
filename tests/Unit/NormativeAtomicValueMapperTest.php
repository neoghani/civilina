<?php

namespace Tests\Unit;

use App\Services\Engineering\NormativeLibrarian\NormativeAtomicValueMapper;
use PHPUnit\Framework\TestCase;

class NormativeAtomicValueMapperTest extends TestCase
{
    public function test_tolerance_keeps_its_first_linked_value_operator_and_unit(): void
    {
        $mapped = (new NormativeAtomicValueMapper())->first(
            '[{"operator":"<","value":"600","unit":"mm"}]'
        );

        $this->assertSame('<', $mapped['operator']);
        $this->assertSame('600', $mapped['value']);
        $this->assertSame('mm', $mapped['unit']);
    }
}
