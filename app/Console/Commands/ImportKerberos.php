<?php

namespace App\Console\Commands;

use App\Services\Kerberos\KerberosImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kerberos:import
    {--connection=kerberos : Connexion vers la base Kerberos (config/database.php)}
    {--environment=PROD : Code de l\'environnement attribué aux serveurs et déploiements importés}
    {--email-domain=kerberos.invalid : Domaine des e-mails générés pour les comptes Kerberos}
    {--dry-run : Simule l\'import puis annule tout}')]
#[Description('Reprend les serveurs, applications et utilisateurs de l\'ancienne application Kerberos')]
class ImportKerberos extends Command
{
    public function handle(): int
    {
        $importer = new KerberosImporter(
            $this->option('connection'),
            $this->option('environment'),
            $this->option('email-domain'),
        );

        $dryRun = (bool) $this->option('dry-run');
        $result = $importer->run($dryRun);

        $this->table(['Élément', 'Nombre'], collect($result->counts)->map(fn ($n, $k) => [$k, $n])->values());

        foreach ($result->warnings as $warning) {
            $this->warn('• '.$warning);
        }

        $dryRun
            ? $this->info('Simulation terminée : aucune donnée n\'a été enregistrée.')
            : $this->info('Import terminé. Relancer la commande ne crée pas de doublons.');

        return self::SUCCESS;
    }
}
