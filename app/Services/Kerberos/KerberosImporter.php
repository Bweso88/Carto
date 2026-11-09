<?php

namespace App\Services\Kerberos;

use App\Enums\ApplicationType;
use App\Enums\Criticality;
use App\Enums\DeploymentRole;
use App\Enums\FlowProtocol;
use App\Enums\ServerKind;
use App\Enums\TechnologyCategory;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\ApplicationFlow;
use App\Models\BusinessDomain;
use App\Models\Environment;
use App\Models\Server;
use App\Models\Site;
use App\Models\Technology;
use App\Models\User;
use App\Models\Vendor;
use App\Support\TechnologyResolver;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reprise des données de l'ancienne application Kerberos (base MySQL « carto »).
 *
 * Idempotent : chaque serveur / application / utilisateur garde son identifiant
 * Kerberos (colonne kerberos_id), on peut donc relancer l'import sans doublons.
 */
final class KerberosImporter
{
    private Connection $source;

    private ImportResult $result;

    /** @var array<int, int> id Kerberos => id MeriMap */
    private array $serverIds = [];

    public function __construct(
        string $connection = 'kerberos',
        private readonly string $environmentCode = 'PROD',
        private readonly string $emailDomain = 'kerberos.invalid',
    ) {
        $this->source = DB::connection($connection);
    }

    public function run(bool $dryRun = false): ImportResult
    {
        $this->result = new ImportResult;

        DB::beginTransaction();
        try {
            $this->importServers();
            $this->linkHypervisors();
            $this->importApplications();
            $this->importUsers();
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->result;
    }

    // ------------------------------------------------------------------ Serveurs

    private function importServers(): void
    {
        $hasCriticality = $this->source->getSchemaBuilder()->hasColumn('serveur', 'criticité');
        $environmentId = Environment::where('code', $this->environmentCode)->value('id');
        if ($environmentId === null) {
            $this->result->warn("Environnement « {$this->environmentCode} » introuvable : serveurs importés sans environnement.");
        }

        $rows = $this->source->table('serveur as s')
            ->leftJoin('type as t', 't.id_type', '=', 's.type')
            ->select('s.*', 't.type as type_label')
            ->orderBy('s.id_serveur')
            ->get();

        foreach ($rows as $row) {
            $hostname = $this->clean($row->serveur);
            if ($hostname === null) {
                $this->result->warn("Serveur Kerberos #{$row->id_serveur} ignoré : nom vide.");

                continue;
            }

            // Un serveur déjà présent (même nom, pas encore lié à Kerberos) est complété plutôt que dupliqué
            $server = Server::withTrashed()->where('kerberos_id', $row->id_serveur)->first()
                ?? Server::withTrashed()->whereNull('kerberos_id')->where('hostname', $hostname)->first()
                ?? new Server;

            $taken = Server::withTrashed()->where('hostname', $hostname)
                ->when($server->exists, fn ($q) => $q->whereKeyNot($server->id))->exists();
            if ($taken) {
                $this->result->warn("Nom « {$hostname} » en double dans Kerberos (#{$row->id_serveur}) : renommé « {$hostname}-k{$row->id_serveur} ».");
                $hostname .= '-k'.$row->id_serveur;
            }

            $server->fill([
                'hostname' => $hostname,
                'kind' => $this->serverKind($row->type_label),
                'criticality' => $hasCriticality ? $this->criticality($row->{'criticité'} ?? null) : null,
                'environment_id' => $server->environment_id ?? $environmentId,
                'site_id' => $this->siteId($row->local ?? null),
                'os_id' => $this->osId($row->os ?? null),
                'description' => $this->clean($row->comment ?? null),
                'kerberos_id' => $row->id_serveur,
            ])->save();

            foreach ($this->parseIps($row->ip ?? null, $row->id_serveur) as $i => $ip) {
                $server->ipAddresses()->updateOrCreate(['address' => $ip], ['is_primary' => $i === 0]);
                $this->result->add('ip_addresses');
            }

            $this->serverIds[(int) $row->id_serveur] = $server->id;
            $this->result->add('servers');
        }
    }

    /**
     * Kerberos note l'hyperviseur dans le commentaire (« Hyperviseur: hyperviseur-02 ») :
     * on en fait un vrai lien VM -> hôte, en créant l'hôte s'il n'existe pas.
     */
    private function linkHypervisors(): void
    {
        $vms = Server::whereIn('id', $this->serverIds)->whereNotNull('description')->get();

        foreach ($vms as $vm) {
            if (! preg_match('/hyperviseur\s*:\s*([\w.\-]+)/iu', (string) $vm->description, $m)) {
                continue;
            }
            $host = Server::whereRaw('LOWER(hostname) = ?', [mb_strtolower($m[1])])->first();
            if ($host === null) {
                $host = Server::create([
                    'hostname' => $m[1],
                    'kind' => ServerKind::Physical,
                    'environment_id' => $vm->environment_id,
                    'site_id' => $vm->site_id,
                    'description' => "Créé automatiquement à l'import : hyperviseur cité par {$vm->hostname}.",
                ]);
                $this->result->add('hosts_created');
            }
            if ($host->id !== $vm->id) {
                $vm->update(['host_id' => $host->id, 'kind' => ServerKind::Virtual]);
            }
        }
    }

    // -------------------------------------------------------------- Applications

    private function importApplications(): void
    {
        $environmentId = Environment::where('code', $this->environmentCode)->value('id');

        $rows = $this->source->table('application as a')
            ->leftJoin('editeur as e', 'e.id_editeur', '=', 'a.editeur')
            ->leftJoin('dependance as d', 'd.id_dep', '=', 'a.dependance')
            ->leftJoin('relation as r', 'r.id_relation', '=', 'a.interaction')
            ->leftJoin('categorie as c', 'c.id_cat', '=', 'a.activite')
            ->leftJoin('bdd as b', 'b.id_bdd', '=', 'a.bdd')
            ->leftJoin('langage as l', 'l.id_langage', '=', 'a.langage')
            ->select('a.*', 'e.editeur as vendor_label', 'd.dependance as dependency_label',
                'r.relation as relation_label', 'c.categorie as domain_label',
                'b.moteur as db_label', 'l.langage as language_label')
            ->orderBy('a.id_ap')
            ->get();

        foreach ($rows as $row) {
            $name = $this->clean($row->app);
            if ($name === null) {
                $this->result->warn("Application Kerberos #{$row->id_ap} ignorée : nom vide.");

                continue;
            }

            $description = null;
            $dependency = $this->clean($row->dependency_label);
            if ($dependency !== null && in_array(mb_strtolower($dependency), ['aucune', 'aucun', 'none', 'n/a', '-'], true)) {
                $dependency = null;
            }
            $relation = $this->clean($row->relation_label);
            if ($relation !== null && $dependency === null) {
                // Kerberos stocke un type d'interaction sans cible : on garde l'info en description
                $description = "Interaction déclarée dans Kerberos : {$relation} (application cible non précisée).";
            }

            $app = Application::withTrashed()->firstOrNew(['kerberos_id' => $row->id_ap]);
            $app->fill([
                'name' => $name,
                'type' => $this->applicationType($row->appser ?? null),
                'vendor_id' => $this->vendorId($row->vendor_label),
                'user_base' => $this->clean($row->utilisateur ?? null),
                'description' => $app->description ?? $description,
            ])->save();
            $this->result->add('applications');

            $technologies = array_filter([
                $this->technologyId(TechnologyCategory::Database, $row->db_label),
                $this->technologyId(TechnologyCategory::Language, $row->language_label),
            ]);
            $app->technologies()->syncWithoutDetaching($technologies);
            if ($domainId = $this->domainId($row->domain_label)) {
                $app->businessDomains()->syncWithoutDetaching([$domainId]);
            }

            if ($row->serveur !== null) {
                $serverId = $this->serverIds[(int) $row->serveur] ?? null;
                if ($serverId === null) {
                    $this->result->warn("« {$name} » pointe vers le serveur Kerberos #{$row->serveur} introuvable.");
                } else {
                    $app->deployments()->firstOrCreate(
                        ['server_id' => $serverId, 'role' => DeploymentRole::Application],
                        ['environment_id' => $environmentId],
                    );
                    $this->result->add('deployments');
                }
            }

            // « Dépendance » Kerberos (AD, SSO, LDAP…) => service technique partagé + flux
            if ($dependency !== null) {
                $service = Application::where('name', $dependency)->first();
                if ($service === null) {
                    $service = Application::create(['name' => $dependency, 'type' => ApplicationType::Technical]);
                    $this->result->add('shared_services');
                }
                ApplicationFlow::firstOrCreate([
                    'source_application_id' => $app->id,
                    'target_application_id' => $service->id,
                    'protocol' => FlowProtocol::guess($relation ?? $dependency),
                ]);
                $this->result->add('flows');
            }
        }
    }

    // --------------------------------------------------------------- Utilisateurs

    private function importUsers(): void
    {
        $schema = $this->source->getSchemaBuilder();
        if (! $schema->hasTable('users')) {
            return;
        }
        $hasRole = $schema->hasColumn('users', 'role');

        foreach ($this->source->table('users')->orderBy('id')->get() as $row) {
            $username = $this->clean($row->username);
            if ($username === null) {
                continue;
            }

            $user = User::firstOrNew(['kerberos_id' => $row->id]);
            $isNew = ! $user->exists;
            $user->fill([
                'name' => $this->clean(trim(($row->firstname ?? '').' '.($row->lastname ?? ''))) ?? $username,
                'email' => $user->email ?? Str::slug($username, '.').'@'.$this->emailDomain,
                'role' => match ($hasRole ? $row->role : null) {
                    'super_admin' => UserRole::Admin,
                    'admin' => UserRole::Editor,
                    default => UserRole::Reader,
                },
                'kerberos_username' => $username,
            ]);
            if ($isNew) {
                $user->password = Str::random(40);   // remplacé ci-dessous si le hash Kerberos est réutilisable
                $user->email_verified_at = now();
            }
            $user->save();

            if ($isNew) {
                $hash = (string) ($row->password ?? '');
                if (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$argon2')) {
                    // Hash bcrypt/argon2 compatible Laravel : l'utilisateur garde son mot de passe
                    DB::table('users')->where('id', $user->id)->update(['password' => $hash]);
                } else {
                    $this->result->warn("Utilisateur « {$username} » : mot de passe non hashé dans Kerberos, réinitialisation nécessaire ({$user->email}).");
                }
            }
            $this->result->add('users');
        }
    }

    // ------------------------------------------------------------------- Helpers

    private function clean(mixed $value): ?string
    {
        // Retire espaces et apostrophes parasites (ex. « 'WS » dans import_serveurs.sql)
        $v = trim((string) $value, " \t\n\r\0\x0B'\"");

        return $v === '' ? null : $v;
    }

    /** @return list<string> */
    private function parseIps(?string $raw, int $kerberosId): array
    {
        $ips = [];
        foreach (preg_split('/[\s,;\/|]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                $ips[] = $candidate;
            } else {
                $this->result->warn("Serveur Kerberos #{$kerberosId} : adresse IP invalide ignorée « {$candidate} ».");
            }
        }

        return array_values(array_unique($ips));
    }

    private function serverKind(?string $label): ServerKind
    {
        $l = mb_strtolower((string) $label);

        return match (true) {
            str_contains($l, 'phys') => ServerKind::Physical,
            str_contains($l, 'cloud') => ServerKind::Cloud,
            str_contains($l, 'conten') || str_contains($l, 'pod') => ServerKind::Container,
            default => ServerKind::Virtual,
        };
    }

    private function criticality(mixed $value): ?Criticality
    {
        return match ((string) $value) {
            '2' => Criticality::Critical,   // Kerberos : « 2 - Critique »
            '1' => Criticality::High,       // Kerberos : « 1 - À surveiller »
            default => null,
        };
    }

    private function applicationType(?string $label): ApplicationType
    {
        return str_contains(mb_strtolower((string) $label), 'service')
            ? ApplicationType::Technical
            : ApplicationType::Business;
    }

    private function siteId(?string $label): ?int
    {
        $name = $this->clean($label);

        return $name === null ? null : Site::firstOrCreate(['name' => $name])->id;
    }

    private function vendorId(?string $label): ?int
    {
        $name = $this->clean($label);

        return $name === null ? null : Vendor::firstOrCreate(['name' => $name])->id;
    }

    private function domainId(?string $label): ?int
    {
        $name = $this->clean($label);

        return $name === null ? null : BusinessDomain::firstOrCreate(['name' => $name])->id;
    }

    private function osId(?string $label): ?int
    {
        // « WS 2012 R2 » -> Windows Server 2012 R2, « Linux Rhel9 » -> Red Hat Enterprise Linux 9…
        return TechnologyResolver::operatingSystem($this->clean($label))?->id;
    }

    private function technologyId(TechnologyCategory $category, ?string $label): ?int
    {
        $name = $this->clean($label);
        if ($name === null) {
            return null;
        }

        // On réutilise une entrée du catalogue si le libellé correspond à « nom version »
        $existing = Technology::where('category', $category)->get()
            ->first(fn (Technology $t) => strcasecmp($t->label(), $name) === 0
                || (strcasecmp($t->name, $name) === 0 && $t->version === ''));

        return $existing->id ?? Technology::create(['category' => $category, 'name' => $name])->id;
    }
}
