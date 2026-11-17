<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_header_keeps_login_link(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Connexion')
            ->assertDontSee(
                'data-civilina-authenticated-user',
                false
            );
    }

    public function test_authenticated_header_shows_user_name_and_logout(): void
    {
        $user =
            User::factory()->create([
                'name' => 'Monsieur Civilina',
            ]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee(
                'data-civilina-authenticated-user',
                false
            )
            ->assertSee('Monsieur Civilina')
            ->assertSee('Déconnexion');
    }

    public function test_logout_ends_authenticated_session(): void
    {
        $user =
            User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }
}
