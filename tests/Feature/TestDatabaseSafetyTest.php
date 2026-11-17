<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RefreshDatabase wipes the database it runs on: the suite must never
 * touch the database configured in .env (forced in phpunit.xml).
 */
class TestDatabaseSafetyTest extends TestCase
{
    public function test_suite_runs_on_in_memory_sqlite_only(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getConfig('database'));
    }
}
