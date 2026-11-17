<?php

namespace App\Console\Commands;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Services\Organizations\OrganizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CivilinaPilotBootstrap extends Command
{
    protected $signature = 'civilina:pilot-bootstrap
        {--name= : Nom de l’utilisateur pilote}
        {--email= : Adresse e-mail du compte pilote}
        {--password= : Mot de passe du compte pilote}
        {--organization= : Nom de l’organisation pilote}
        {--organization-slug= : Slug explicite de l’organisation}';

    protected $description =
        'Crée ou prépare un compte pilote Civilina avec une organisation et un rôle Owner.';

    public function handle(
        OrganizationService $organizations
    ): int {
        // Local/demo tool: the password is typed or passed as an option and
        // an existing account's password is overwritten. Real pilot access
        // uses civilina:pilot-invite.
        if (app()->environment('production')) {
            $this->error('Commande réservée au poste local. En production : php artisan civilina:pilot-invite.');

            return self::FAILURE;
        }

        $name = trim(
            (string) (
                $this->option('name')
                ?: $this->ask('Nom du compte pilote')
            )
        );

        $email = Str::lower(
            trim(
                (string) (
                    $this->option('email')
                    ?: $this->ask('E-mail du compte pilote')
                )
            )
        );

        $password =
            (string) (
                $this->option('password')
                ?: $this->secret('Mot de passe du compte pilote')
            );

        $organizationName = trim(
            (string) (
                $this->option('organization')
                ?: $this->ask('Nom de l’organisation pilote')
            )
        );

        $organizationSlug = trim(
            (string) (
                $this->option('organization-slug')
                ?: Str::slug($organizationName)
            )
        );

        try {
            validator(
                [
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'organization' => $organizationName,
                    'organization_slug' => $organizationSlug,
                ],
                [
                    'name' => [
                        'required',
                        'string',
                        'max:255',
                    ],
                    'email' => [
                        'required',
                        'email',
                        'max:255',
                    ],
                    'password' => [
                        'required',
                        'string',
                        'min:12',
                    ],
                    'organization' => [
                        'required',
                        'string',
                        'max:255',
                    ],
                    'organization_slug' => [
                        'required',
                        'string',
                        'max:255',
                    ],
                ]
            )->validate();
        } catch (ValidationException $exception) {
            foreach (
                $exception->errors()
                as $messages
            ) {
                foreach (
                    $messages
                    as $message
                ) {
                    $this->error(
                        $message
                    );
                }
            }

            return self::FAILURE;
        }

        [
            $user,
            $organization,
        ] = DB::transaction(
            function () use (
                $organizations,
                $name,
                $email,
                $password,
                $organizationName,
                $organizationSlug
            ): array {
                $user =
                    User::query()
                        ->firstOrNew([
                            'email' => $email,
                        ]);

                $user->name =
                    $name;

                $user->password =
                    Hash::make(
                        $password
                    );

                $user->save();

                $organization =
                    Organization::query()
                        ->where(
                            'slug',
                            $organizationSlug
                        )
                        ->first();

                if (! $organization) {
                    $organization =
                        $organizations
                            ->createForOwner(
                                $user,
                                $organizationName,
                                $organizationSlug
                            );
                } else {
                    if (
                        $organization->name !==
                        $organizationName
                    ) {
                        $organization->name =
                            $organizationName;

                        $organization->save();
                    }

                    $organizations
                        ->addOrUpdateMember(
                            $organization,
                            $user,
                            OrganizationRole::Owner,
                            OrganizationMembershipStatus::Active
                        );
                }

                return [
                    $user->refresh(),
                    $organization->refresh(),
                ];
            }
        );

        $this->newLine();

        $this->info(
            'Compte pilote Civilina prêt.'
        );

        $this->table(
            [
                'Élément',
                'Valeur',
            ],
            [
                [
                    'Utilisateur',
                    $user->name,
                ],
                [
                    'E-mail',
                    $user->email,
                ],
                [
                    'Organisation',
                    $organization->name,
                ],
                [
                    'Slug organisation',
                    $organization->slug,
                ],
                [
                    'Rôle',
                    OrganizationRole::Owner->value,
                ],
            ]
        );

        $this->warn(
            'Le mot de passe n’est jamais réaffiché.'
        );

        return self::SUCCESS;
    }
}
