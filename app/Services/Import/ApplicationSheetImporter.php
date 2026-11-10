<?php

namespace App\Services\Import;

use App\Enums\ApplicationType;
use App\Enums\DeploymentRole;
use App\Enums\FlowProtocol;
use App\Enums\TechnologyCategory;
use App\Models\Application;
use App\Models\ApplicationFlow;
use App\Models\BusinessDomain;
use App\Models\Server;
use App\Models\Vendor;
use App\Support\Normalize;
use App\Support\TechnologyResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Import d'applications depuis un tableau (Excel, CSV ou export HTML « .xls » de Kerberos), en deux temps :
 *   1. analyze() : lit, interprète et compare avec la base — n'écrit RIEN ;
 *   2. apply()   : exécute le plan dans une transaction.
 *
 * Plusieurs lignes portant le même nom d'application (ex. « Exchange » sur trois serveurs) donnent
 * UNE application déployée sur plusieurs serveurs. En mode « remplacer », toutes les applications
 * existantes (et leurs déploiements, flux, liens) sont supprimées avant l'import.
 */
final class ApplicationSheetImporter
{
    /** En-têtes reconnus (comparés sans accents ni majuscules). */
    private const COLUMNS = [
        'id' => ['id', 'id kerberos', 'kerberos', 'identifiant'],
        'name' => ['application', 'applications', 'nom', 'nom de l application', 'app', 'logiciel'],
        'server' => ['serveur', 'serveurs', 'hostname', 'server', 'machine', 'hebergement'],
        'editor' => ['editeur', 'origine', 'fournisseur', 'vendor'],
        'dependency' => ['dependance', 'dependances', 'depend de', 'dependency', 'dependencies'],
        'interaction' => ['interaction', 'type d interaction', 'mode'],
        'activity' => ['activite', 'categorie', 'type', 'type d application'],
        'database' => ['bdd', 'base de donnees', 'sgbd', 'database', 'db'],
        'language' => ['langage', 'langages', 'language', 'technologie'],
        'users' => ['utilisateur', 'utilisateurs', 'users', 'direction', 'directions', 'domaine', 'domaines', 'domaine metier'],
        'middleware' => ['app service', 'serveur d application', 'middleware', 'serveur web', 'service'],
        'criticality' => ['criticite', 'criticality', 'tier'],
        'description' => ['description', 'commentaire', 'commentaires', 'remarques'],
    ];

    private const LABELS = [
        'id' => 'Identifiant', 'name' => 'Application', 'server' => 'Serveur', 'editor' => 'Éditeur', 'dependency' => 'Dépendance',
        'interaction' => 'Interaction', 'activity' => 'Activité', 'database' => 'Base de données', 'language' => 'Langage',
        'users' => 'Utilisateurs', 'middleware' => 'Serveur d\'application', 'criticality' => 'Criticité', 'description' => 'Description',
    ];

    /** Valeurs signifiant « rien » ou « fourni par l'éditeur » (comparées via Normalize::key). */
    private const EMPTY_VALUES = ['', 'n a', 'na', 'nd', 'sans', 'aucun', 'aucune', 'none', 'editeur', 'non', 'neant'];

    /** Noms canoniques du catalogue (clé = libellé en minuscules). */
    private const TECHNOLOGIES = [
        'database' => [
            'sql server' => 'SQL Server', 'sqlserver' => 'SQL Server', 'mssql' => 'SQL Server', 'oracle' => 'Oracle Database',
            'mysql' => 'MySQL', 'mariadb' => 'MariaDB', 'postgresql' => 'PostgreSQL', 'postgres' => 'PostgreSQL',
            'sqlite' => 'SQLite', 'mongodb' => 'MongoDB', 'access' => 'Microsoft Access', 'informix' => 'Informix',
        ],
        'language' => [
            'php' => 'PHP', 'java' => 'Java', 'c#' => 'C#', 'csharp' => 'C#', '.net' => 'C#', 'visual basic' => 'Visual Basic',
            'vb' => 'Visual Basic', 'vb.net' => 'Visual Basic', 'delphi' => 'Delphi', 'genero' => 'Genero', 'python' => 'Python',
            'javascript' => 'JavaScript / TypeScript', 'typescript' => 'JavaScript / TypeScript', 'cobol' => 'COBOL', 'windev' => 'WinDev',
        ],
        'middleware' => [
            'iis' => 'IIS', 'apache' => 'Apache HTTP Server', 'glassfish' => 'GlassFish', 'tomcat' => 'Apache Tomcat',
            'nginx' => 'Nginx', 'jboss' => 'JBoss', 'wildfly' => 'WildFly', 'weblogic' => 'WebLogic', 'websphere' => 'WebSphere',
        ],
    ];

    /**
     * @param  list<list<string|null>>  $rows  première ligne = en-têtes
     * @return array<string, mixed>
     */
    public function analyze(array $rows, bool $replace = false): array
    {
        if (count($rows) < 2) {
            throw new RuntimeException('Le fichier ne contient aucune ligne de données.');
        }

        [$map, $unknown] = $this->mapHeaders(array_shift($rows));
        if (! isset($map['name'])) {
            throw new RuntimeException('Colonne du nom d\'application introuvable : la première ligne doit contenir un en-tête « Application » (ou « Nom »).');
        }

        // 1. Regroupement des lignes par application
        $groups = [];
        $errors = [];
        foreach ($rows as $i => $cells) {
            $line = $i + 2;
            $get = fn (string $field) => isset($map[$field]) ? ($cells[$map[$field]] ?? null) : null;
            $name = trim((string) $get('name'));
            if ($name === '') {
                $errors[] = $this->row($line, null, 'error', errors: ['Nom d\'application vide.']);

                continue;
            }

            $key = Normalize::key($name);
            $g = $groups[$key] ?? ['lines' => [], 'name' => $name, 'ids' => [], 'servers' => [], 'dependencies' => [], 'users' => [],
                'technologies' => [], 'editor' => null, 'interaction' => null, 'activity' => null,
                'criticality' => null, 'description' => null];
            $g['lines'][] = $line;
            if (ctype_digit((string) $get('id'))) {
                $g['ids'][] = (int) $get('id');
            }
            foreach ($this->split($get('server'), '/[,;\n]+/') as $s) {
                $g['servers'][Normalize::key($s)] ??= $s;
            }
            foreach ($this->split($get('dependency'), '/[,;\n]+/') as $d) {
                $g['dependencies'][Normalize::key($d)] ??= $d;
            }
            foreach ($this->split($get('users'), '/[\/,;+\n]+/') as $u) {
                $g['users'][mb_strtolower($u)] ??= $u;
            }
            foreach (['database', 'language', 'middleware'] as $field) {
                foreach ($this->split($get($field), '/[,;\/+\n]+/') as $label) {
                    if ($tech = $this->technology($field, $label)) {
                        $g['technologies'][$field.':'.$tech] = ['category' => $field, 'name' => $tech];
                    }
                }
            }
            foreach (['editor', 'interaction', 'activity', 'criticality', 'description'] as $field) {
                $g[$field] ??= $this->clean($get($field));
            }
            $groups[$key] = $g;
        }

        // 2. Référentiels existants
        $servers = Server::query()->get(['id', 'hostname', 'environment_id']);
        $existing = $replace ? collect() : Application::withTrashed()->with(['servers:id,hostname', 'technologies', 'businessDomains'])->get()
            ->keyBy(fn (Application $a) => Normalize::key($a->name));
        $domains = BusinessDomain::all();
        $usedKerberosIds = $replace ? collect() : Application::withTrashed()->whereNotNull('kerberos_id')->pluck('id', 'kerberos_id');

        // Noms d'applications connus (fichier + base) pour résoudre les dépendances
        $known = collect($groups)->map(fn ($g) => $g['name'])->union($existing->map(fn (Application $a) => $a->name));
        $autoCreated = [];
        $newDomains = [];
        $allMissingServers = [];

        $plan = [];
        foreach ($groups as $key => $g) {
            $warnings = [];
            $line = $g['lines'][0];
            if (count($g['lines']) > 1) {
                $warnings[] = count($g['lines']).' lignes regroupées (lignes '.implode(', ', $g['lines']).').';
            }

            // Serveurs
            $deployOn = [];
            $missingServers = [];
            foreach ($g['servers'] as $label) {
                $server = $this->findServer($servers, $label);
                if ($server === null) {
                    $missingServers[] = $label;
                    $warnings[] = "Serveur « {$label} » absent de MeriMap : pas de déploiement (importez d'abord le serveur).";
                } else {
                    if (strcasecmp($server->hostname, $label) !== 0) {
                        $warnings[] = "Serveur « {$label} » associé à {$server->hostname} (nom approchant).";
                    }
                    $deployOn[$server->id] = $server->hostname;
                }
            }

            // Utilisateurs -> domaines métier
            [$domainNames, $userBase] = $this->domains(array_values($g['users']));
            foreach ($domainNames as $i => $code) {
                $match = $domains->first(fn (BusinessDomain $d) => Normalize::key($d->name) === Normalize::key($code)
                    || str_contains(Normalize::key($d->name), '('.Normalize::key($code).')')
                    || preg_match('/^'.preg_quote(Normalize::key($code), '/').'( |$)/', Normalize::key($d->name)));
                $domainNames[$i] = $match?->name ?? $code;
            }
            foreach ($domainNames as $n) {
                if (! $domains->contains('name', $n)) {
                    $newDomains[$n] = true;
                }
            }
            foreach ($missingServers as $s) {
                $allMissingServers[$s] = ($allMissingServers[$s] ?? 0) + 1;
            }

            // Dépendances
            $dependencies = [];
            foreach ($g['dependencies'] as $label) {
                $target = $this->resolveDependency($label, $known, $key);
                if ($target === false) {
                    continue; // l'application dépend d'elle-même
                }
                if ($target === null) {
                    $target = $label;
                    $autoCreated[Normalize::key($label)] ??= ['name' => $label, 'cited_by' => []];
                    $autoCreated[Normalize::key($label)]['cited_by'][] = $g['name'];
                } elseif (Normalize::key($target) !== Normalize::key($label)) {
                    $warnings[] = "Dépendance « {$label} » rattachée à « {$target} ».";
                }
                $dependencies[] = ['label' => $label, 'target' => $target];
            }

            $editorKey = Normalize::key($g['editor']);
            $internal = match ($editorKey) {
                'interne', 'internal', 'maison', 'developpement interne' => true,
                'externe', 'external', '' => $editorKey === '' ? null : false,
                default => false,
            };
            $vendor = ! in_array($editorKey, ['', 'interne', 'internal', 'maison', 'developpement interne', 'externe', 'external'], true) ? $g['editor'] : null;

            $kerberosId = $g['ids'] ? min($g['ids']) : null;
            $current = $existing->get($key);
            if ($kerberosId !== null && $usedKerberosIds->has($kerberosId) && $usedKerberosIds->get($kerberosId) !== $current?->id) {
                $kerberosId = null; // identifiant déjà porté par une autre application
            }

            $data = [
                'name' => $g['name'],
                'kerberos_id' => $kerberosId,
                'type' => $this->type($g['activity'])->value,
                'activity' => $g['activity'],
                'interaction' => $g['interaction'],
                'internal' => $internal,
                'vendor' => $vendor,
                'criticality' => Normalize::criticality($g['criticality'])?->value,
                'servers' => array_values($deployOn),
                'server_ids' => array_keys($deployOn),
                'missing_servers' => $missingServers,
                'technologies' => array_values($g['technologies']),
                'domains' => array_values(array_unique($domainNames)),
                'user_base' => $userBase,
                'dependencies' => $dependencies,
                'description' => $this->description($g, $internal, $missingServers),
            ];

            $changes = $current ? $this->changes($current, $data) : [];
            $action = match (true) {
                $current === null => 'create',
                $current->trashed() || $changes !== [] => 'update',
                default => 'unchanged',
            };
            $plan[] = $this->row($line, $g['name'], $action, $data, $changes, $warnings);
        }

        // Applications citées comme dépendance mais absentes du fichier et de la base
        foreach ($autoCreated as $auto) {
            $cited = array_values(array_unique($auto['cited_by']));
            $plan[] = $this->row(null, $auto['name'], 'create', [
                'name' => $auto['name'], 'kerberos_id' => null, 'type' => ApplicationType::Business->value, 'activity' => null,
                'interaction' => null, 'internal' => null, 'vendor' => null, 'criticality' => null, 'servers' => [], 'server_ids' => [],
                'missing_servers' => [], 'technologies' => [], 'domains' => [], 'user_base' => null, 'dependencies' => [],
                'description' => 'Créée automatiquement : citée comme dépendance par '.implode(', ', $cited).'.',
            ], warnings: ['Absente du fichier : créée car '.count($cited).' application(s) en dépendent. Complétez sa fiche ensuite.'], auto: true);
        }

        $plan = array_merge($plan, $errors);
        $summary = ['create' => 0, 'update' => 0, 'unchanged' => 0, 'error' => 0];
        foreach ($plan as $row) {
            $summary[$row['action']]++;
        }

        return [
            'replace' => $replace,
            'existing' => $replace ? [
                'applications' => Application::withTrashed()->count(),
                'deployments' => DB::table('deployments')->count(),
                'flows' => ApplicationFlow::count(),
            ] : null,
            'new_domains' => array_keys($newDomains),
            'missing_servers' => $allMissingServers,
            'columns' => array_intersect_key(self::LABELS, $map),
            'unknown_columns' => $unknown,
            'rows' => $plan,
            'summary' => $summary,
        ];
    }

    /**
     * Exécute un plan produit par analyze().
     *
     * @param  array<string, mixed>  $plan
     * @return array{deleted: int, create: int, update: int, deployments: int, flows: int}
     */
    public function apply(array $plan): array
    {
        $done = ['deleted' => 0, 'create' => 0, 'update' => 0, 'deployments' => 0, 'flows' => 0];

        DB::transaction(function () use ($plan, &$done) {
            if ($plan['replace']) {
                $done['deleted'] = Application::withTrashed()->count();
                ApplicationFlow::query()->delete();
                DB::table('deployments')->delete();
                DB::table('application_technology')->delete();
                DB::table('application_business_domain')->delete();
                Application::withTrashed()->forceDelete();
            }

            $rows = array_values(array_filter($plan['rows'], fn ($r) => in_array($r['action'], ['create', 'update'], true)));
            $byKey = [];

            // Applications d'abord (les dépendances peuvent pointer vers une ligne plus bas)
            foreach ($rows as $row) {
                $d = $row['data'];
                $app = $plan['replace'] ? null : Application::withTrashed()->get()->first(fn (Application $a) => Normalize::key($a->name) === Normalize::key($d['name']));
                $app ??= new Application(['name' => $d['name']]);
                if ($app->trashed()) {
                    $app->restore();
                }
                $isNew = ! $app->exists;
                $app->fill(array_filter([
                    'type' => $isNew || $d['activity'] ? $d['type'] : null,
                    'criticality' => $d['criticality'],
                    'kerberos_id' => $d['kerberos_id'],
                    'user_base' => $d['user_base'],
                    'vendor_id' => $d['vendor'] ? Vendor::firstOrCreate(['name' => $d['vendor']])->id : null,
                    'description' => $app->description ? null : $d['description'],
                ], fn ($v) => $v !== null && $v !== ''))->save();

                $app->technologies()->syncWithoutDetaching(collect($d['technologies'])
                    ->map(fn ($t) => TechnologyResolver::resolve(TechnologyCategory::from($t['category']), $t['name'])->id)->all());
                $app->businessDomains()->syncWithoutDetaching(collect($d['domains'])
                    ->map(fn ($name) => BusinessDomain::firstOrCreate(['name' => $name])->id)->all());

                foreach ($d['server_ids'] as $serverId) {
                    $server = Server::find($serverId);
                    if ($server && ! $app->deployments()->where('server_id', $serverId)->exists()) {
                        $app->deployments()->create(['server_id' => $serverId, 'environment_id' => $server->environment_id, 'role' => DeploymentRole::Application]);
                        $done['deployments']++;
                    }
                }

                $byKey[Normalize::key($d['name'])] = $app;
                $done[$row['action']]++;
            }

            // Flux de dépendance
            foreach ($rows as $row) {
                $source = $byKey[Normalize::key($row['data']['name'])];
                foreach ($row['data']['dependencies'] as $dep) {
                    $target = $byKey[Normalize::key($dep['target'])]
                        ?? Application::get()->first(fn (Application $a) => Normalize::key($a->name) === Normalize::key($dep['target']));
                    if ($target === null || $target->id === $source->id) {
                        continue;
                    }
                    $flow = ApplicationFlow::firstOrCreate(
                        ['source_application_id' => $source->id, 'target_application_id' => $target->id, 'protocol' => FlowProtocol::guess($row['data']['interaction'])],
                        ['description' => 'Dépendance déclarée dans le fichier importé'.($row['data']['interaction'] ? " (interaction : {$row['data']['interaction']})" : '').'.'],
                    );
                    $done['flows'] += $flow->wasRecentlyCreated ? 1 : 0;
                }
            }
        });

        return $done;
    }

    // ----------------------------------------------------------------------

    /**
     * @param  array<string, mixed>|null  $data
     * @param  list<array{field: string, before: string|null, after: string}>  $changes
     * @param  list<string>  $warnings
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function row(?int $line, ?string $name, string $action, ?array $data = null, array $changes = [], array $warnings = [], array $errors = [], bool $auto = false): array
    {
        return compact('line', 'name', 'action', 'data', 'changes', 'warnings', 'errors', 'auto');
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return in_array(Normalize::key($value), self::EMPTY_VALUES, true) ? null : $value;
    }

    /** @return list<string> */
    private function split(?string $value, string $pattern): array
    {
        return array_values(array_filter(array_map(fn ($v) => $this->clean($v), preg_split($pattern, (string) $value) ?: [])));
    }

    private function technology(string $field, string $label): ?string
    {
        $catalog = self::TECHNOLOGIES[$field];
        $lower = mb_strtolower(trim($label));
        if (isset($catalog[$lower])) {
            return $catalog[$lower];
        }
        // Le serveur d'application n'accepte que des produits connus (la colonne contient souvent du bruit)
        if ($field === 'middleware') {
            return null;
        }

        return preg_match('/^[\p{L}0-9][\p{L}0-9 .#+\-]{0,40}$/u', $label) ? $label : null;
    }

    private function type(?string $activity): ApplicationType
    {
        return match (Normalize::key($activity)) {
            'support', 'technique', 'infrastructure', 'outil', 'securite' => ApplicationType::Technical,
            'saas', 'cloud' => ApplicationType::Saas,
            default => ApplicationType::Business,
        };
    }

    /**
     * « CLM/DAI », « DIDSI_DT », « ALL », « All_RH Workflow » -> domaines métier + public utilisateur.
     *
     * @param  list<string>  $users
     * @return array{0: list<string>, 1: string|null}
     */
    private function domains(array $users): array
    {
        $domains = [];
        $everyone = [];
        foreach ($users as $u) {
            if (preg_match('/^(all|tous|toutes)(?=[_\s-]|$)[_\s-]*(.*)$/i', $u, $m)) {
                $everyone[] = trim($m[2]);

                continue;
            }
            $code = trim(preg_split('/_/', $u)[0]);
            if ($code !== '') {
                $domains[mb_strtoupper($code) === $code || mb_strlen($code) <= 6 ? mb_strtoupper($code) : $code] = true;
            }
        }

        $userBase = match (true) {
            $everyone === [] => $users ? implode(' / ', $users) : null,
            array_filter($everyone) === [] => 'Tous les utilisateurs',
            default => 'Tous ('.implode(', ', array_filter($everyone)).')',
        };

        return [array_keys($domains), $userBase !== null ? mb_substr($userBase, 0, 255) : null];
    }

    /** @param Collection<int, Server> $servers */
    private function findServer(Collection $servers, string $label): ?Server
    {
        $exact = $servers->first(fn (Server $s) => strcasecmp($s->hostname, $label) === 0);
        if ($exact) {
            return $exact;
        }
        // Noms approchants : « DATA01 » ↔ « Srv-Data01 »
        $strip = fn (string $v) => preg_replace('/^(srv|serveur|server)\s+/', '', Normalize::key($v));
        $close = $servers->filter(fn (Server $s) => $strip($s->hostname) === $strip($label));

        return $close->count() === 1 ? $close->first() : null;
    }

    /**
     * Nom de l'application visée par une dépendance, null si inconnue, false si elle-même.
     *
     * @param  Collection<string, string>  $known  clé normalisée => nom
     */
    private function resolveDependency(string $label, Collection $known, string $selfKey): string|false|null
    {
        $key = Normalize::key($label);
        $singular = fn (string $k) => preg_replace('/s\b/', '', $k);

        $target = $known->get($key)
            ?? $known->first(fn ($name, $k) => $singular($k) === $singular($key));
        if ($target === null) {
            $prefixed = $known->filter(fn ($name, $k) => str_starts_with($k, $key.' '));
            $target = $prefixed->count() === 1 ? $prefixed->first() : $prefixed->first(fn ($name, $k) => str_contains($k, 'prod'));
        }

        return $target !== null && Normalize::key($target) === $selfKey ? false : $target;
    }

    /**
     * @param  array<string, mixed>  $g
     * @param  list<string>  $missingServers
     */
    private function description(array $g, ?bool $internal, array $missingServers): ?string
    {
        $parts = array_filter([
            $g['description'],
            $g['activity'] ? "Activité : {$g['activity']}." : null,
            $internal === null ? null : ($internal ? 'Développement interne.' : 'Solution éditeur (externe).'),
            $g['interaction'] ? "Interaction : {$g['interaction']}." : null,
            $missingServers ? 'Serveur(s) cité(s) dans l\'import, absent(s) de MeriMap : '.implode(', ', $missingServers).'.' : null,
        ]);

        return $parts ? implode(' ', $parts) : null;
    }

    /**
     * Ce que l'import ajoutera à une application existante.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{field: string, before: string|null, after: string}>
     */
    private function changes(Application $app, array $data): array
    {
        $changes = [];
        $newServers = array_diff($data['servers'], $app->servers->pluck('hostname')->all());
        if ($newServers) {
            $changes[] = ['field' => 'Serveurs', 'before' => $app->servers->pluck('hostname')->implode(', ') ?: null, 'after' => '+ '.implode(', ', $newServers)];
        }
        $techs = $app->technologies->map(fn ($t) => mb_strtolower($t->name))->all();
        $newTechs = array_filter(array_column($data['technologies'], 'name'), fn ($n) => ! in_array(mb_strtolower($n), $techs, true));
        if ($newTechs) {
            $changes[] = ['field' => 'Technologies', 'before' => null, 'after' => '+ '.implode(', ', $newTechs)];
        }
        $newDomains = array_diff($data['domains'], $app->businessDomains->pluck('name')->all());
        if ($newDomains) {
            $changes[] = ['field' => 'Domaines', 'before' => $app->businessDomains->pluck('name')->implode(', ') ?: null, 'after' => '+ '.implode(', ', $newDomains)];
        }
        if ($data['activity'] && $app->type->value !== $data['type']) {
            $changes[] = ['field' => 'Type', 'before' => $app->type->label(), 'after' => ApplicationType::from($data['type'])->label()];
        }
        if ($data['user_base'] && $app->user_base !== $data['user_base']) {
            $changes[] = ['field' => 'Utilisateurs', 'before' => $app->user_base, 'after' => $data['user_base']];
        }

        return $changes;
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
                if (in_array($key, $aliases, true) && ! isset($map[$candidate])) {
                    $field = $candidate;
                    break;
                }
            }
            $field !== null ? $map[$field] = $index : $unknown[] = (string) $header;
        }

        return [$map, $unknown];
    }
}
