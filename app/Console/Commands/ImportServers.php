<?php

namespace App\Console\Commands;

use App\Services\Import\ServerSheetImporter;
use App\Support\Spreadsheet\SpreadsheetReader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('merimap:import-servers
    {file : Fichier Excel (.xlsx) ou CSV, première ligne = en-têtes (Serveur, IP, OS, Type, Localisation, Criticité…)}
    {--environment=PROD : Environnement des nouveaux serveurs si le fichier ne le précise pas}
    {--dry-run : Affiche ce qui serait fait, sans rien enregistrer}')]
#[Description('Importe ou met à jour des serveurs depuis un fichier Excel ou CSV')]
class ImportServers extends Command
{
    public function handle(ServerSheetImporter $importer): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("Fichier introuvable : {$path}");

            return self::FAILURE;
        }

        try {
            $plan = $importer->analyze(SpreadsheetReader::read($path), (string) $this->option('environment'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $labels = ['create' => 'Création', 'update' => 'Mise à jour', 'restore' => 'Restauration', 'unchanged' => 'Inchangé', 'error' => 'Erreur'];
        $this->table(['Ligne', 'Serveur', 'Action', 'Détail'], collect($plan['rows'])
            ->reject(fn ($r) => $r['action'] === 'unchanged')
            ->map(fn ($r) => [
                $r['line'], $r['hostname'] ?? '—', $labels[$r['action']],
                implode(' · ', array_merge(
                    $r['errors'],
                    array_map(fn ($c) => "{$c['field']} : ".($c['before'] ?? '∅')." → {$c['after']}", $r['changes']),
                    $r['warnings'],
                )),
            ]));
        $this->line(collect($plan['summary'])->map(fn ($n, $k) => "{$labels[$k]} : {$n}")->implode('   '));

        if ($this->option('dry-run')) {
            $this->info('Simulation : rien n\'a été enregistré.');

            return self::SUCCESS;
        }

        $done = $importer->apply($plan);
        $this->info("Import terminé : {$done['create']} créé(s), {$done['update']} mis à jour, {$done['restore']} restauré(s).");

        return $plan['summary']['error'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
