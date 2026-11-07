<?php

namespace App\Http\Controllers\Cmdb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cmdb\ServerRequest;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use App\Support\CmdbOptions;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServerController extends Controller
{
    /** Colonnes triables : paramètre « sort » => colonne SQL */
    private const SORTS = ['hostname' => 'hostname', 'kind' => 'kind', 'status' => 'status', 'criticality' => 'criticality', 'updated' => 'updated_at'];

    public function index(Request $request): Response
    {
        $servers = $this->filteredQuery($request)
            ->with(['primaryIp', 'environment', 'site', 'os'])
            ->withCount(['deployments', 'guests'])
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Server $s) => ServerResource::make($s)->resolve());

        return Inertia::render('servers/Index', [
            'servers' => $servers,
            'filters' => $this->filters($request),
            'options' => [
                ...CmdbOptions::enums(),
                'environments' => CmdbOptions::environments(),
                'sites' => CmdbOptions::sites(),
                'businessDomains' => CmdbOptions::businessDomains(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filteredQuery($request)
            ->with(['ipAddresses', 'environment', 'site', 'os', 'host', 'ownerTeam'])
            ->withCount('deployments')
            ->lazy()
            ->map(fn (Server $s) => [
                $s->hostname, $s->kind->label(), $s->status->label(), $s->criticality?->label(),
                $s->ipAddresses->pluck('address')->implode(' '), $s->os?->label(), $s->environment?->name,
                $s->site?->name, $s->host?->hostname, $s->ownerTeam?->name, $s->deployments_count, $s->description,
            ]);

        return Csv::download('serveurs_'.now()->format('Ymd_His').'.csv',
            ['Serveur', 'Type', 'Statut', 'Criticité', 'Adresses IP', 'Système', 'Environnement', 'Site', 'Hôte', 'Équipe', 'Applications', 'Description'],
            $rows);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('servers/Form', [
            'server' => null,
            'values' => ['kind' => $request->query('kind', 'virtual'), 'status' => 'active', 'host_id' => $request->query('host_id')],
            'options' => $this->formOptions(),
        ]);
    }

    public function store(ServerRequest $request): RedirectResponse
    {
        $server = DB::transaction(function () use ($request) {
            $server = Server::create($request->safe()->except('ip_addresses'));
            $this->syncIps($server, $request->validated('ip_addresses'));

            return $server;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Serveur {$server->hostname} créé."]);

        return to_route('servers.show', $server);
    }

    public function show(Server $server): Response
    {
        $server->load(['ipAddresses', 'environment', 'site', 'os', 'ownerTeam', 'manufacturer', 'host', 'guests'])
            ->loadCount(['deployments', 'guests']);

        $deployments = $server->deployments()
            ->with(['application.businessDomains', 'environment'])
            ->get()
            ->filter(fn ($d) => $d->application !== null);

        $domains = $deployments->flatMap(fn ($d) => $d->application->businessDomains)
            ->unique('id')->sortBy('name')->map->only('id', 'name')->values();

        $deployments = $deployments
            ->values()
            ->map(fn ($d) => [
                'id' => $d->id,
                'role' => ['value' => $d->role->value, 'label' => $d->role->label()],
                'environment' => $d->environment?->only('code', 'name'),
                'application' => $d->application->only('id', 'code', 'name'),
                'domains' => $d->application->businessDomains->pluck('name')->all(),
                'criticality' => $d->application->criticality ? ['value' => $d->application->criticality->value, 'label' => $d->application->criticality->label()] : null,
            ]);

        return Inertia::render('servers/Show', [
            'server' => ServerResource::make($server)->resolve(),
            'deployments' => $deployments,
            'domains' => $domains,
        ]);
    }

    public function edit(Server $server): Response
    {
        $server->load('ipAddresses');

        return Inertia::render('servers/Form', [
            'server' => ['id' => $server->id, 'hostname' => $server->hostname],
            'values' => [
                ...$server->only([
                    'hostname', 'fqdn', 'environment_id', 'site_id', 'os_id', 'owner_team_id', 'host_id',
                    'manufacturer_id', 'model', 'serial_number', 'cpu_cores', 'ram_gb', 'storage_gb', 'description',
                ]),
                'kind' => $server->kind->value,
                'status' => $server->status->value,
                'criticality' => $server->criticality?->value,
                'commissioned_at' => $server->commissioned_at?->toDateString(),
                'warranty_end_at' => $server->warranty_end_at?->toDateString(),
                'ip_addresses_text' => $server->ipAddresses->sortByDesc('is_primary')->pluck('address')->implode("\n"),
            ],
            'options' => $this->formOptions($server),
        ]);
    }

    public function update(ServerRequest $request, Server $server): RedirectResponse
    {
        DB::transaction(function () use ($request, $server) {
            $server->update($request->safe()->except('ip_addresses'));
            $this->syncIps($server, $request->validated('ip_addresses'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Serveur {$server->hostname} mis à jour."]);

        return to_route('servers.show', $server);
    }

    public function destroy(Server $server): RedirectResponse
    {
        $server->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => "Serveur {$server->hostname} supprimé."]);

        return to_route('servers.index');
    }

    // ----------------------------------------------------------------------

    /** @return array<string, string|null> */
    private function filters(Request $request): array
    {
        return $request->only(['search', 'kind', 'status', 'criticality', 'environment', 'site', 'domain', 'sort']);
    }

    /** @return Builder<Server> */
    private function filteredQuery(Request $request): Builder
    {
        $f = $this->filters($request);
        $sort = (string) ($f['sort'] ?? 'hostname');
        $column = self::SORTS[ltrim($sort, '-')] ?? 'hostname';

        return Server::query()
            ->search($f['search'] ?? null)
            ->when($f['kind'] ?? null, fn ($q, $v) => $q->where('kind', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['criticality'] ?? null, fn ($q, $v) => $v === 'none' ? $q->whereNull('criticality') : $q->where('criticality', $v))
            ->when($f['environment'] ?? null, fn ($q, $v) => $q->where('environment_id', $v))
            ->when($f['site'] ?? null, fn ($q, $v) => $q->where('site_id', $v))
            // Serveurs qui hébergent au moins une application de ce domaine
            ->when($f['domain'] ?? null, fn ($q, $v) => $q->whereHas('deployments.application.businessDomains', fn ($d) => $d->where('business_domains.id', $v)))
            ->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderBy('id');
    }

    /** @return array<string, mixed> */
    private function formOptions(?Server $server = null): array
    {
        return [
            ...CmdbOptions::enums(),
            'environments' => CmdbOptions::environments(),
            'sites' => CmdbOptions::sites(),
            'operatingSystems' => CmdbOptions::operatingSystems(),
            'teams' => CmdbOptions::teams(),
            'vendors' => CmdbOptions::vendors(),
            'hosts' => Server::query()
                ->whereIn('kind', ['physical', 'cloud'])
                ->when($server, fn ($q) => $q->whereKeyNot($server->id))
                ->orderBy('hostname')->get(['id', 'hostname'])
                ->map(fn ($s) => ['value' => (string) $s->id, 'label' => $s->hostname])->all(),
        ];
    }

    /** @param list<string> $ips première adresse = adresse principale */
    private function syncIps(Server $server, array $ips): void
    {
        $server->ipAddresses()->whereNotIn('address', $ips)->delete();
        foreach ($ips as $i => $ip) {
            $server->ipAddresses()->updateOrCreate(['address' => $ip], ['is_primary' => $i === 0]);
        }
    }
}
