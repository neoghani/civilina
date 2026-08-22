<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Organizations\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CivilinaSessionAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_available(): void
    {
        $this
            ->get('/login')
            ->assertOk()
            ->assertSee(
                'CIVILINA'
            )
            ->assertSee(
                'Se connecter'
            );
    }

    public function test_valid_credentials_create_authenticated_session(): void
    {
        $user = User::factory()->create([
            'email' =>
                'owner@civilina.test',

            'password' =>
                Hash::make(
                    'Civilina-Test-2026!'
                ),
        ]);

        $this
            ->post(
                '/login',
                [
                    'email' =>
                        'owner@civilina.test',

                    'password' =>
                        'Civilina-Test-2026!',
                ]
            )
            ->assertRedirect('/');

        $this->assertAuthenticatedAs(
            $user
        );
    }

    public function test_invalid_credentials_do_not_authenticate(): void
    {
        User::factory()->create([
            'email' =>
                'owner@civilina.test',

            'password' =>
                Hash::make(
                    'Correct-Password'
                ),
        ]);

        $this
            ->from('/login')
            ->post(
                '/login',
                [
                    'email' =>
                        'owner@civilina.test',

                    'password' =>
                        'Wrong-Password',
                ]
            )
            ->assertRedirect(
                '/login'
            )
            ->assertSessionHasErrors(
                'email'
            );

        $this->assertGuest();
    }

    public function test_logout_invalidates_authenticated_session(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post('/logout')
            ->assertRedirect(
                '/login'
            );

        $this->assertGuest();
    }

    public function test_browser_session_authenticates_organization_api(): void
    {
        $user = User::factory()->create([
            'email' =>
                'pilot@civilina.test',

            'password' =>
                Hash::make(
                    'Pilot-Password-2026!'
                ),
        ]);

        $organization = app(
            OrganizationService::class
        )->createForOwner(
            $user,
            'Civilina Pilot'
        );

        $this->post(
            '/login',
            [
                'email' =>
                    'pilot@civilina.test',

                'password' =>
                    'Pilot-Password-2026!',
            ]
        )->assertRedirect('/');

        $this
            ->getJson(
                '/api/organizations'
            )
            ->assertOk()
            ->assertJsonFragment([
                'public_id' =>
                    $organization->public_id,
            ]);
    }

    public function test_unauthenticated_browser_cannot_access_organization_api(): void
    {
        $this
            ->getJson(
                '/api/organizations'
            )
            ->assertUnauthorized();
    }
}
