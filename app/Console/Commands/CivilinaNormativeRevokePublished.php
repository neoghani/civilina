<?php

namespace App\Console\Commands;

use App\Services\Engineering\NormativeLibrarian\NormativeControlledPublicationService;
use Illuminate\Console\Command;

class CivilinaNormativeRevokePublished extends Command
{
    protected $signature = 'civilina:normative-registry:revoke
        {id : ID du registre publié}
        {--reason= : Motif obligatoire de révocation}
        {--actor=SYSTEM_CONTROLLED : Auteur de la revocation}';

    protected $description = 'Révoque une exigence publiée sans supprimer son historique.';

    public function handle(NormativeControlledPublicationService $service): int
    {
        $reason = trim((string) $this->option('reason'));

        if ($reason === '') {
            $this->error('Le motif --reason est obligatoire.');
            return self::FAILURE;
        }

        $service->revoke(
            (int) $this->argument('id'),
            $reason,
            trim((string) $this->option('actor')) ?: 'SYSTEM_CONTROLLED'
        );

        $this->info('Exigence publiée révoquée. Historique conservé.');

        return self::SUCCESS;
    }
}
