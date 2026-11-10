<?php

namespace App\Console\Commands;

use App\Services\Import\ApplicationSheetImporter;
use App\Support\Spreadsheet\SpreadsheetReader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('merimap:import-applications
    {file : Fichier Excel (.xlsx, .xls exporté de Kerberos) ou CSV, première ligne = en-têtes (Application, Serveur, Dépendance…)}
    {--replace : Supprime TOUTES les applications existantes (déploiements, flux compris) avant l\'import}
    {--dry-run : Affiche ce qui serait fait, sans rien enregistrer}')]
#[Description('Importe ou met à jour des applications depuis un fichier Excel ou CSV')]
class ImportApplications extends Command
{
    public function handle(ApplicationSheetImporter $importer): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("Fichier introuvable : {$path}");

            return self::FAILURE;
        }

        try {
            $plan = $importer->analyze(SpreadsheetReader::read($path), (bool) $this->option('replace'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $labels = ['create' => 'Création', 'update' => 'Mise à jour', 'unchanged' => 'Inchangée', 'error' => 'Erreur'];
        $this->table(['Ligne', 'Application', 'Action', 'Serveurs', 'Remarques'], collect($plan['rows'])
            ->reject(fn ($r) => $r['action'] === 'unchanged')
            ->map(fn ($r) => [
                $r['line'] ?? '—', $r['name'] ?? '—', $labels[$r['action']],
                implode(', ', $r['data']['servers'] ?? []),
                implode(' · ', array_merge($r['errors'], array_map(fn ($c) => "{$c['field']} : {$c['after']}", $r['changes']), $r['warnings'])),
            ]));
        $this->line(collect($plan['summary'])->map(fn ($n, $k) => "{$labels[$k]} : {$n}")->implode('   '));
        if ($plan['new_domains']) {
            $this->line('Domaines métier à créer : '.implode(', ', $plan['new_domains']));
        }

        if ($plan['replace']) {
            $e = $plan['existing'];
            $this->warn("Mode remplacement : {$e['applications']} application(s), {$e['deployments']} déploiement(s) et {$e['flows']} flux seront supprimés.");
        }
        if ($this->option('dry-run')) {
            $this->info('Simulation : rien n\'a été enregistré.');

            return self::SUCCESS;
        }
        if ($plan['replace'] && $this->input->isInteractive() && ! $this->confirm('Confirmer la suppression puis l\'import ?')) {
            return self::FAILURE;
        }

        $done = $importer->apply($plan);
        $this->info("Import terminé : {$done['deleted']} supprimée(s), {$done['create']} créée(s), {$done['update']} mise(s) à jour, {$done['deployments']} déploiement(s), {$done['flows']} dépendance(s).");

        return $plan['summary']['error'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
