<?php

namespace App\Console\Commands;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\CivilinaInvitation;
use App\Services\Engineering\EngineeringProjectService;
use App\Services\Organizations\OrganizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Opens a pilot engineering firm: one organization per firm, the invited
 * person gets a link to choose their own password. No password is ever
 * typed, passed as an option or displayed.
 *
 *   php artisan civilina:pilot-invite --name="…" --email=… --organization="BET …"
 *   php artisan civilina:pilot-invite --name="…" --email=… --organization-slug=bet-x --join --role=member
 */
class CivilinaPilotInvite extends Command
{
    protected $signature = 'civilina:pilot-invite
        {--name= : Nom de la personne invitée}
        {--email= : Adresse e-mail de la personne invitée}
        {--organization= : Nom du bureau d’études (nouvelle organisation)}
        {--organization-slug= : Slug de l’organisation (par défaut : dérivé du nom)}
        {--role=owner : owner, admin, manager, member ou viewer}
        {--project-code=P-001 : Code du premier projet (nouvelle organisation)}
        {--project-name=Projet pilote : Nom du premier projet (nouvelle organisation)}
        {--join : Ajouter la personne à une organisation existante (désignée par son slug)}
        {--send : Envoyer l’invitation par e-mail (sinon le lien est affiché pour être transmis)}';

    protected $description = 'Ouvre un accès pilote : organisation du bureau d’études + invitation (la personne choisit son mot de passe).';

    public function handle(OrganizationService $organizations, EngineeringProjectService $projects): int
    {
        $name = trim((string) $this->option('name'));
        $email = Str::lower(trim((string) $this->option('email')));
        $organizationName = trim((string) $this->option('organization'));
        $slug = trim((string) ($this->option('organization-slug') ?: Str::slug($organizationName)));
        $role = OrganizationRole::tryFrom(Str::lower((string) $this->option('role')));
        $join = (bool) $this->option('join');

        $validator = validator(
            ['name' => $name, 'email' => $email, 'organization' => $organizationName, 'slug' => $slug],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'organization' => [$join ? 'nullable' : 'required', 'string', 'max:255'],
                'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        if ($role === null) {
            $this->error('Rôle inconnu : utilisez owner, admin, manager, member ou viewer.');

            return self::FAILURE;
        }

        $organization = Organization::query()->where('slug', $slug)->first();

        if ($organization && ! $join) {
            $this->error("L’organisation « {$slug} » existe déjà. Ajoutez --join pour y inviter une personne.");

            return self::FAILURE;
        }

        if (! $organization && $join) {
            $this->error("Aucune organisation « {$slug} » : retirez --join pour la créer.");

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        $projectCode = trim((string) $this->option('project-code')) ?: 'P-001';
        $projectName = trim((string) $this->option('project-name')) ?: 'Projet pilote';

        [$user, $organization, $created] = DB::transaction(function () use ($organizations, $projects, $existing, $name, $email, $organization, $organizationName, $slug, $role, $projectCode, $projectName): array {
            $user = $existing;
            $created = false;

            if (! $user) {
                // Random unusable password until the person chooses theirs.
                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(64)),
                ]);
                $created = true;
            }

            if (! $organization) {
                $organization = $organizations->createForOwner($user, $organizationName, $slug);
                // Without a project the Engineering entry has nowhere to go.
                $projects->createProject($organization, $projectCode, $projectName);
            }

            $organizations->addOrUpdateMember($organization, $user, $role, OrganizationMembershipStatus::Active);

            return [$user, $organization->refresh(), $created];
        });

        $this->info('Accès pilote prêt.');
        $this->table(['Élément', 'Valeur'], [
            ['Personne', $user->name],
            ['E-mail', $user->email],
            ['Organisation', $organization->name],
            ['Slug', $organization->slug],
            ['Rôle', $role->value],
            ['Compte', $created ? 'créé' : 'existant (mot de passe inchangé)'],
        ]);

        if (! $created) {
            $this->line('La personne a déjà un compte : elle se connecte avec son mot de passe actuel.');

            return self::SUCCESS;
        }

        $token = Password::broker('invitations')->createToken($user);
        $url = route('invitation.create', ['token' => $token, 'email' => $user->email]);
        $hours = intdiv((int) config('auth.passwords.invitations.expire', 4320), 60);

        if ($this->option('send')) {
            $user->notify(new CivilinaInvitation($url, $organization->name, $hours));
            $this->info("Invitation envoyée à {$user->email} (lien valable {$hours} h).");
        } else {
            $this->warn("Lien d’activation personnel, valable {$hours} h. À transmettre uniquement à {$user->email} :");
            $this->line($url);
        }

        return self::SUCCESS;
    }
}
