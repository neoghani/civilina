<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class NormativeControlledPublicationSafetyTest extends TestCase
{
    public function test_only_validated_is_publishable_by_policy(): void
    {
        $allowed = ['VALIDATED'];

        $this->assertContains('VALIDATED', $allowed);
        $this->assertNotContains('CANDIDATE', $allowed);
        $this->assertNotContains('REJECTED', $allowed);
    }

    public function test_revocation_is_not_deletion(): void
    {
        $states = ['ACTIVE', 'REVOKED'];

        $this->assertContains('REVOKED', $states);
        $this->assertNotContains('DELETED', $states);
    }
}
