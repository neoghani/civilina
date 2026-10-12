<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class NormativePhysicalLibraryIndexPathTest extends TestCase
{
    public function test_run_index_strategy_uses_versioned_directory(): void
    {
        $root = 'D:\\Civilina_Normes_Classees';
        $run = 'run_20260826_120000';

        $path = $root.'\\_CIVILINA_INDEX\\'.$run.'\\duplicates.csv';

        $this->assertStringContainsString('_CIVILINA_INDEX', $path);
        $this->assertStringContainsString($run, $path);
        $this->assertStringEndsWith('duplicates.csv', $path);
    }
}
