<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\CivilinaInvitation;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class CivilinaPilotInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_creates_organization_owner_and_sends_activation_link(): void
    {
        Notification::fake();

        $this->artisan('civilina:pilot-invite', [
            '--name' => 'Responsable BET',
            '--email' => 'Responsable@BET-Pilote.test',
            '--organization' => 'BET Pilote Structures',
            '--send' => true,
        ])->assertSuccessful();

        $user = User::query()->where('email', 'responsable@bet-pilote.test')->firstOrFail();
        $organization = Organization::query()->where('slug', 'bet-pilote-structures')->firstOrFail();
        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        $this->assertSame(OrganizationRole::Owner, $membership->role);

        $url = null;
        Notification::assertSentTo($user, CivilinaInvitation::class, function (CivilinaInvitation $notification) use (&$url, $organization): bool {
            $url = $notification->url;

            return $notification->organizationName === $organization->name && $notification->expireHours === 72;
        });

        // The invitee chooses the password through the link.
        $this->get($url)->assertOk()->assertSee('Activer mon compte');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $token = basename((string) parse_url($url, PHP_URL_PATH));

        $this->post('/invitation', [
            'token' => $token,
            'email' => $query['email'],
            'password' => 'Une-phrase-de-passe-solide',
            'password_confirmation' => 'Une-phrase-de-passe-solide',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check('Une-phrase-de-passe-solide', $user->fresh()->password));

        // The link is single use.
        $this->post('/invitation', [
            'token' => $token,
            'email' => $query['email'],
            'password' => 'Autre-phrase-de-passe-solide',
            'password_confirmation' => 'Autre-phrase-de-passe-solide',
        ])->assertSessionHasErrors('email');
    }

    public function test_new_firm_gets_a_first_project_and_lands_in_its_workspace(): void
    {
        $this->artisan('civilina:pilot-invite', [
            '--name' => 'Responsable BET',
            '--email' => 'resp@bet.test',
            '--organization' => 'BET Routes',
            '--project-code' => 'RN-12',
            '--project-name' => 'Réhabilitation RN12',
        ])->assertSuccessful();

        $organization = Organization::query()->where('slug', 'bet-routes')->firstOrFail();
        $project = \App\Models\Project::query()->where('organization_id', $organization->getKey())->firstOrFail();
        $this->assertSame('RN-12', $project->code);

        $user = User::query()->where('email', 'resp@bet.test')->firstOrFail();

        $this->actingAs($user)
            ->get('/engineering')
            ->assertRedirect(route('engineering.workspace', [
                'organization' => $organization->public_id,
                'project' => $project->public_id,
            ]));
    }

    public function test_invitation_link_stays_valid_beyond_the_reset_delay(): void
    {
        $user = User::factory()->create();
        $token = Password::broker('invitations')->createToken($user);

        $this->travel(48)->hours();

        $this->post('/invitation', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Une-phrase-de-passe-solide',
            'password_confirmation' => 'Une-phrase-de-passe-solide',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check('Une-phrase-de-passe-solide', $user->fresh()->password));
    }

    public function test_invite_never_touches_an_existing_account_password(): void
    {
        $existing = User::factory()->create(['email' => 'deja@bet.test', 'password' => Hash::make('Mot-de-passe-actuel-123')]);

        $this->artisan('civilina:pilot-invite', [
            '--name' => 'Autre nom',
            '--email' => 'deja@bet.test',
            '--organization' => 'BET Deux',
        ])->assertSuccessful();

        $this->assertTrue(Hash::check('Mot-de-passe-actuel-123', $existing->fresh()->password));
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_invite_refuses_existing_slug_without_join_and_unknown_slug_with_join(): void
    {
        $this->artisan('civilina:pilot-invite', [
            '--name' => 'A', '--email' => 'a@bet.test', '--organization' => 'BET Alpha',
        ])->assertSuccessful();

        $this->artisan('civilina:pilot-invite', [
            '--name' => 'B', '--email' => 'b@bet.test', '--organization' => 'BET Alpha',
        ])->assertFailed();

        $this->artisan('civilina:pilot-invite', [
            '--name' => 'C', '--email' => 'c@bet.test', '--organization-slug' => 'bet-inconnu', '--join' => true,
        ])->assertFailed();

        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseMissing('users', ['email' => 'b@bet.test']);
        $this->assertDatabaseMissing('users', ['email' => 'c@bet.test']);
    }

    public function test_join_adds_colleague_with_requested_role(): void
    {
        $this->artisan('civilina:pilot-invite', [
            '--name' => 'A', '--email' => 'a@bet.test', '--organization' => 'BET Alpha',
        ])->assertSuccessful();

        $this->artisan('civilina:pilot-invite', [
            '--name' => 'Ingénieur', '--email' => 'ing@bet.test', '--organization-slug' => 'bet-alpha',
            '--join' => true, '--role' => 'member',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'ing@bet.test')->firstOrFail();
        $membership = OrganizationMembership::query()->where('user_id', $user->getKey())->firstOrFail();

        $this->assertSame(OrganizationRole::Member, $membership->role);
        $this->assertDatabaseCount('organizations', 1);
    }

    public function test_invite_rejects_unknown_role(): void
    {
        $this->artisan('civilina:pilot-invite', [
            '--name' => 'A', '--email' => 'a@bet.test', '--organization' => 'BET Alpha', '--role' => 'superadmin',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_forgot_password_does_not_reveal_whether_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'connu@bet.test']);

        $known = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'connu@bet.test']);
        $unknown = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'inconnu@bet.test']);

        $known->assertRedirect('/forgot-password');
        $unknown->assertRedirect('/forgot-password');
        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_reset_link_expires_after_sixty_minutes(): void
    {
        $user = User::factory()->create();
        $token = Password::broker('users')->createToken($user);

        $this->travel(61)->minutes();

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Une-phrase-de-passe-solide',
            'password_confirmation' => 'Une-phrase-de-passe-solide',
        ])->assertSessionHasErrors('email');
    }

    public function test_short_password_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = Password::broker('invitations')->createToken($user);

        $this->post('/invitation', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'court',
            'password_confirmation' => 'court',
        ])->assertSessionHasErrors('password');
    }

    public function test_login_page_links_to_forgot_password(): void
    {
        $this->get('/login')->assertOk()->assertSee('Mot de passe oublié');
    }

    public function test_demo_tools_refuse_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('civilina:pilot-bootstrap', [
            '--name' => 'X', '--email' => 'x@bet.test', '--password' => 'Civilina-Pilot-2026!', '--organization' => 'X',
        ])->assertFailed();

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
    }
}
