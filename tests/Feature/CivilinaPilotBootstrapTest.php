<?php

namespace Tests\Feature;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CivilinaPilotBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_pilot_user_organization_and_owner_membership(): void
    {
        $this
            ->artisan(
                'civilina:pilot-bootstrap',
                [
                    '--name' =>
                        'Pilot Owner',

                    '--email' =>
                        'pilot@civilina.test',

                    '--password' =>
                        'Civilina-Pilot-2026!',

                    '--organization' =>
                        'Civilina Pilot',

                    '--organization-slug' =>
                        'civilina-pilot',
                ]
            )
            ->assertSuccessful();

        $user =
            User::query()
                ->where(
                    'email',
                    'pilot@civilina.test'
                )
                ->firstOrFail();

        $organization =
            Organization::query()
                ->where(
                    'slug',
                    'civilina-pilot'
                )
                ->firstOrFail();

        $membership =
            OrganizationMembership::query()
                ->where(
                    'organization_id',
                    $organization->id
                )
                ->where(
                    'user_id',
                    $user->id
                )
                ->firstOrFail();

        $this->assertSame(
            'Pilot Owner',
            $user->name
        );

        $this->assertTrue(
            Hash::check(
                'Civilina-Pilot-2026!',
                $user->password
            )
        );

        $this->assertSame(
            OrganizationRole::Owner,
            $membership->role
        );

        $this->assertSame(
            OrganizationMembershipStatus::Active,
            $membership->status
        );
    }

    public function test_command_is_idempotent_for_same_email_and_organization_slug(): void
    {
        $arguments = [
            '--name' =>
                'Pilot Owner',

            '--email' =>
                'pilot@civilina.test',

            '--password' =>
                'Civilina-Pilot-2026!',

            '--organization' =>
                'Civilina Pilot',

            '--organization-slug' =>
                'civilina-pilot',
        ];

        $this
            ->artisan(
                'civilina:pilot-bootstrap',
                $arguments
            )
            ->assertSuccessful();

        $this
            ->artisan(
                'civilina:pilot-bootstrap',
                $arguments
            )
            ->assertSuccessful();

        $this->assertDatabaseCount(
            'users',
            1
        );

        $this->assertDatabaseCount(
            'organizations',
            1
        );

        $this->assertDatabaseCount(
            'organization_memberships',
            1
        );
    }

    public function test_command_rejects_short_password(): void
    {
        $this
            ->artisan(
                'civilina:pilot-bootstrap',
                [
                    '--name' =>
                        'Pilot Owner',

                    '--email' =>
                        'pilot@civilina.test',

                    '--password' =>
                        'short',

                    '--organization' =>
                        'Civilina Pilot',
                ]
            )
            ->assertFailed();

        $this->assertDatabaseCount(
            'users',
            0
        );

        $this->assertDatabaseCount(
            'organizations',
            0
        );
    }
}
