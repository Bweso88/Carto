<?php

namespace App\Services\Import;

use App\Enums\ServerKind;
use App\Models\Environment;
use App\Models\Server;
use App\Models\Site;
use App\Support\Normalize;
use App\Support\TechnologyResolver;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Import de serveurs depuis un tableau (Excel ou CSV), en deux temps :
 *   1. analyze() : lit, interprète et compare avec la base — n'écrit RIEN, produit un plan (aperçu) ;
 *   2. apply()   : exécute le plan dans une transaction.
 *
 * Un serveur déjà présent (même nom d'hôte, sans tenir compte des majuscules) est complété :
 * les cellules vides du fichier n'effacent jamais une valeur existante.
 */
final class ServerSheetImporter
{
    /** En-têtes reconnus (comparés sans accents ni majuscules). */
    private const COLUMNS = [
        'hostname' => ['serveur', 'serveurs', 'nom', 'nom du serveur', 'hostname', 'host name', 'server', 'machine', 'nom d hote'],
        'ip' => ['ip', 'adresse ip', 'adresses ip', 'ip address', 'adresse', '@ip'],
        'os' => ['os', 'systeme', 'systeme d exploitation', 'operating system', 'version os'],
        'kind' => ['type', 'type de serveur', 'nature'],
        'site' => ['localisation', 'site', 'emplacement', 'salle', 'datacenter', 'lieu'],
        'criticality' => ['criticite', 'criticality', 'tier', 'niveau de criticite'],
        'environment' => ['environnement', 'env', 'environment'],
        'description' => ['description', 'commentaire', 'commentaires', 'notes', 'remarque', 'remarques', 'role'],
        'host' => ['hote', 'hyperviseur', 'host', 'serveur hote', 'hote physique'],
    ];

    /**
     * @param  list<list<string|null>>  $rows  première ligne = en-têtes
     * @return array{columns: array<string, string>, unknown_columns: list<string>, rows: list<array<string, mixed>>, summary: array<string, int>}
     */
    public function analyze(array $rows, string $defaultEnvironment = 'PROD'): array
    {
        if (count($rows) < 2) {
            throw new RuntimeException('Le fichier ne contient aucune ligne de données.');
        }

        [$map, $unknown] = $this->mapHeaders(array_shift($rows));
        if (! isset($map['hostname'])) {
            throw new RuntimeException('Colonne du nom de serveur introuvable : la première ligne doit contenir un en-tête « Serveur » (ou « Nom », « Hostname »).');
        }

        $environments = Environment::all();
        $defaultEnv = $environments->first(fn ($e) => strcasecmp($e->code, $defaultEnvironment) === 0);
        $existing = Server::withTrashed()->with(['ipAddresses', 'os', 'site', 'environment', 'host'])->get()
            ->keyBy(fn (Server $s) => mb_strtolower($s->hostname));
        $inFile = [];
        $plan = [];

        foreach ($rows as $i => $cells) {
            $line = $i + 2; // numéro de ligne tel qu'affiché dans Excel
            $get = fn (string $field) => isset($map[$field]) ? ($cells[$map[$field]] ?? null) : null;
            $warnings = [];

            $hostname = trim((string) $get('hostname'));
            if ($hostname === '') {
                $plan[] = ['line' => $line, 'hostname' => null, 'action' => 'error', 'errors' => ['Nom de serveur vide.'], 'warnings' => [], 'changes' => []];

                continue;
            }
            if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9.\-_]*$/', $hostname)) {
                $plan[] = ['line' => $line, 'hostname' => $hostname, 'action' => 'error', 'errors' => ['Nom de serveur invalide (lettres, chiffres, points, tirets et soulignés uniquement).'], 'warnings' => [], 'changes' => []];

                continue;
            }
            $key = mb_strtolower($hostname);
            if (isset($inFile[$key])) {
                $plan[] = ['line' => $line, 'hostname' => $hostname, 'action' => 'error', 'errors' => ["Doublon : déjà présent ligne {$inFile[$key]}."], 'warnings' => [], 'changes' => []];

                continue;
            }
            $inFile[$key] = $line;

            // Interprétation des cellules
            $ips = [];
            foreach (preg_split('/[\s,;\/|]+/', (string) $get('ip'), -1, PREG_SPLIT_NO_EMPTY) as $candidate) {
                filter_var($candidate, FILTER_VALIDATE_IP) ? $ips[] = $candidate : $warnings[] = "Adresse IP ignorée : « {$candidate} ».";
            }

            $osLabel = $get('os');
            $os = Normalize::operatingSystem($osLabel);
            $osText = $os ? trim(implode(' ', $os)) : null;

            $kind = Normalize::serverKind($get('kind'));
            if ($get('kind') !== null && $kind === null) {
                $warnings[] = "Type « {$get('kind')} » non reconnu : « Virtuel » par défaut.";
            }

            $criticality = Normalize::criticality($get('criticality'));

            $envCode = null;
            if ($label = $get('environment')) {
                $env = $environments->first(fn ($e) => Normalize::key($e->code) === Normalize::key($label) || Normalize::key($e->name) === Normalize::key($label));
                $env ? $envCode = $env->code : $warnings[] = "Environnement « {$label} » inconnu : environnement par défaut.";
            } elseif (preg_match('/test|recette|preprod|(^|[-_.])dev([-_.]|\d|$)/i', $hostname, $m)) {
                // Nom évocateur d'un serveur hors production (ex. « mucagirrh-test »)
                $guess = match (true) {
                    stripos($m[0], 'preprod') !== false => 'PREPROD',
                    stripos($m[0], 'dev') !== false => 'DEV',
                    default => 'RECETTE',
                };
                if ($environments->contains('code', $guess)) {
                    $envCode = $guess;
                    $warnings[] = "Environnement déduit du nom : {$guess}.";
                }
            }

            $current = $existing->get($key);
            $new = [
                'hostname' => $hostname,
                'ips' => $ips,
                'os' => $osText,
                'os_label' => $osLabel,
                'kind' => ($kind ?? ($current ? null : ServerKind::Virtual))?->value,
                'site' => $get('site'),
                'criticality' => $criticality?->value,
                'environment' => $envCode ?? ($current ? null : $defaultEnv?->code),
                'description' => $get('description'),
                'host' => $get('host'),
            ];

            if ($os && ($catalog = TechnologyResolver::operatingSystem($osLabel, create: false)) && $catalog->isObsolete()) {
                $warnings[] = "{$catalog->label()} est en fin de vie.";
            }

            // Comparaison avec l'existant : seules les valeurs renseignées dans le fichier comptent
            $changes = [];
            $compare = function (string $field, ?string $before, ?string $after, string $label) use (&$changes) {
                if ($after !== null && $after !== '' && mb_strtolower((string) $before) !== mb_strtolower($after)) {
                    $changes[] = ['field' => $label, 'before' => $before, 'after' => $after];
                }
            };
            $compare('kind', $current?->kind?->label(), $new['kind'] ? ServerKind::from($new['kind'])->label() : null, 'Type');
            $compare('os', $current?->os?->label(), $osText, 'Système');
            $compare('site', $current?->site?->name, $new['site'], 'Site');
            $compare('criticality', $current?->criticality?->label(), $criticality?->label(), 'Criticité');
            $compare('environment', $current?->environment?->code, $new['environment'], 'Environnement');
            $compare('description', $current?->description, $new['description'], 'Description');
            $compare('host', $current?->host?->hostname, $new['host'], 'Hôte');
            $missingIps = array_values(array_diff($ips, $current?->ipAddresses->pluck('address')->all() ?? []));
            if ($missingIps) {
                $changes[] = ['field' => 'Adresse IP', 'before' => $current?->ipAddresses->pluck('address')->implode(', ') ?: null, 'after' => implode(', ', $missingIps)];
            }

            $action = match (true) {
                $current === null => 'create',
                $current->trashed() => 'restore',
                $changes !== [] => 'update',
                default => 'unchanged',
            };

            $plan[] = [
                'line' => $line,
                'hostname' => $hostname,
                'action' => $action,
                'data' => $new,
                'changes' => $current === null ? [] : $changes,
                'warnings' => $warnings,
                'errors' => [],
            ];
        }

        // Hôtes : doivent exister en base ou dans le fichier
        foreach ($plan as &$row) {
            $host = $row['data']['host'] ?? null;
            if ($host && ! $existing->has(mb_strtolower($host)) && ! isset($inFile[mb_strtolower($host)])) {
                $row['warnings'][] = "Hôte « {$host} » introuvable : il sera créé comme serveur physique.";
            }
        }
        unset($row);

        $summary = ['create' => 0, 'update' => 0, 'restore' => 0, 'unchanged' => 0, 'error' => 0];
        foreach ($plan as $row) {
            $summary[$row['action']]++;
        }

        return [
            'columns' => $this->columnLabels($map),
            'unknown_columns' => $unknown,
            'rows' => $plan,
            'summary' => $summary,
        ];
    }

    /**
     * Exécute un plan produit par analyze().
     *
     * @param  array{rows: list<array<string, mixed>>}  $plan
     * @return array<string, int> nombre de serveurs créés, mis à jour, restaurés
     */
    public function apply(array $plan): array
    {
        $done = ['create' => 0, 'update' => 0, 'restore' => 0];

        DB::transaction(function () use ($plan, &$done) {
            $environments = Environment::all()->keyBy('code');
            $hostLinks = [];

            foreach ($plan['rows'] as $row) {
                if (! in_array($row['action'], ['create', 'update', 'restore'], true)) {
                    continue;
                }
                $d = $row['data'];
                $server = Server::withTrashed()->whereRaw('LOWER(hostname) = ?', [mb_strtolower($d['hostname'])])->first()
                    ?? new Server(['hostname' => $d['hostname']]);

                if ($server->trashed()) {
                    $server->restore();
                }
                $fill = array_filter([
                    'kind' => $d['kind'],
                    'criticality' => $d['criticality'],
                    'description' => $d['description'],
                    'environment_id' => $d['environment'] ? $environments->get($d['environment'])?->id : null,
                    'site_id' => $d['site'] ? Site::firstOrCreate(['name' => $d['site']])->id : null,
                    'os_id' => $d['os_label'] ? TechnologyResolver::operatingSystem($d['os_label'])?->id : null,
                ], fn ($v) => $v !== null && $v !== '');
                $server->fill($fill)->save();

                $hasPrimary = $server->ipAddresses()->where('is_primary', true)->exists();
                foreach ($d['ips'] as $i => $ip) {
                    $server->ipAddresses()->firstOrCreate(['address' => $ip], ['is_primary' => ! $hasPrimary && $i === 0]);
                }

                if ($d['host']) {
                    $hostLinks[$server->id] = $d['host'];
                }
                $done[$row['action']]++;
            }

            // Liens VM -> hôte, une fois tous les serveurs créés
            foreach ($hostLinks as $serverId => $hostname) {
                $host = Server::whereRaw('LOWER(hostname) = ?', [mb_strtolower($hostname)])->first()
                    ?? Server::create(['hostname' => $hostname, 'kind' => ServerKind::Physical, 'description' => 'Créé automatiquement lors d\'un import (hôte cité).']);
                if ($host->id !== $serverId) {
                    Server::whereKey($serverId)->update(['host_id' => $host->id]);
                }
            }
        });

        return $done;
    }

    /**
     * @param  list<string|null>  $headers
     * @return array{0: array<string, int>, 1: list<string>}
     */
    private function mapHeaders(array $headers): array
    {
        $map = [];
        $unknown = [];
        foreach ($headers as $index => $header) {
            $key = Normalize::key($header);
            if ($key === '') {
                continue;
            }
            $field = null;
            foreach (self::COLUMNS as $candidate => $aliases) {
                if (in_array($key, $aliases, true)) {
                    $field = $candidate;
                    break;
                }
            }
            if ($field !== null && ! isset($map[$field])) {
                $map[$field] = $index;
            } else {
                $unknown[] = (string) $header;
            }
        }

        return [$map, $unknown];
    }

    /**
     * @param  array<string, int>  $map
     * @return array<string, string>
     */
    private function columnLabels(array $map): array
    {
        $labels = ['hostname' => 'Nom du serveur', 'ip' => 'Adresse IP', 'os' => 'Système', 'kind' => 'Type', 'site' => 'Site',
            'criticality' => 'Criticité', 'environment' => 'Environnement', 'description' => 'Description', 'host' => 'Hôte'];

        return array_intersect_key($labels, $map);
    }
}
