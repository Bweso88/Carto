<?php

namespace App\Services\Topology;

use App\Models\Application;
use App\Models\ApplicationFlow;
use App\Models\Deployment;
use App\Models\Server;
use App\Services\Impact\ImpactReport;
use Illuminate\Support\Collection;

/**
 * Construit le graphe (nœuds + liens) affiché par l'écran Topologie.
 *
 * Nœuds : applications (« app-ID ») et serveurs (« srv-ID »).
 * Liens : flux applicatifs (source consomme cible), déploiements (app -> serveur),
 * hébergement (VM -> hôte physique).
 */
final class TopologyGraph
{
    /** Au-delà, on refuse d'afficher le graphe complet : il faut filtrer. */
    public const MAX_NODES = 400;

    /**
     * @param  array{environment?: int|null, domain?: int|null, focus?: int|null, depth?: int, servers?: bool}  $options
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>, truncated: bool}
     */
    public function build(array $options = []): array
    {
        $withServers = $options['servers'] ?? true;
        $environment = $options['environment'] ?? null;

        $apps = Application::query()
            ->with('technologies')
            ->when($options['domain'] ?? null, fn ($q, $d) => $q->whereRelation('businessDomains', 'business_domains.id', $d))
            ->get()->keyBy('id');

        if ($focus = $options['focus'] ?? null) {
            $keep = $this->neighbourhood((int) $focus, max(1, min(5, (int) ($options['depth'] ?? 2))));
            $apps = Application::with('technologies')->whereIn('id', $keep)->get()->keyBy('id');
        }

        $flows = ApplicationFlow::query()
            ->whereIn('source_application_id', $apps->keys())
            ->whereIn('target_application_id', $apps->keys())
            ->get();

        $deployments = collect();
        $servers = collect();
        if ($withServers) {
            $deployments = Deployment::query()
                ->whereIn('application_id', $apps->keys())
                ->when($environment, fn ($q, $e) => $q->where(fn ($q) => $q->where('environment_id', $e)->orWhereNull('environment_id')))
                ->get();
            $servers = $this->withHosts(Server::with(['os', 'primaryIp'])->whereIn('id', $deployments->pluck('server_id'))->get());
        }

        return $this->assemble($apps, $servers, $flows, $deployments);
    }

    /**
     * Graphe restreint à une simulation : serveurs en panne + applications touchées.
     *
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>, truncated: bool}
     */
    public function forImpact(ImpactReport $report): array
    {
        $apps = Application::with('technologies')->whereIn('id', $report->applications->keys())->get()->keyBy('id');
        $failedIds = $report->failedServers->pluck('id');

        $flows = ApplicationFlow::query()
            ->whereIn('source_application_id', $apps->keys())
            ->whereIn('target_application_id', $apps->keys())
            ->get();
        $deployments = Deployment::query()->whereIn('server_id', $failedIds)->whereIn('application_id', $apps->keys())->get();
        $servers = Server::with(['os', 'primaryIp'])->whereIn('id', $failedIds)->get();

        $graph = $this->assemble($apps, $servers, $flows, $deployments);

        // Annotation de l'état de chaque nœud pour la coloration
        foreach ($graph['nodes'] as &$node) {
            $node['data']['impact'] = $node['type'] === 'server'
                ? 'down'
                : $report->statusOf($node['data']['id'])?->value;
            $node['data']['root'] = $node['type'] === 'application' && ($report->applications->get($node['data']['id'])?->isRoot ?? false);
        }
        unset($node);

        return $graph;
    }

    /**
     * Applications à distance <= $depth (dans les deux sens des flux) de l'application donnée.
     *
     * @return list<int>
     */
    public function neighbourhood(int $applicationId, int $depth): array
    {
        $seen = [$applicationId => true];
        $frontier = [$applicationId];
        for ($i = 0; $i < $depth && $frontier; $i++) {
            $flows = ApplicationFlow::query()
                ->whereIn('source_application_id', $frontier)
                ->orWhereIn('target_application_id', $frontier)
                ->get(['source_application_id', 'target_application_id']);
            $next = [];
            foreach ($flows as $f) {
                foreach ([$f->source_application_id, $f->target_application_id] as $id) {
                    if (! isset($seen[$id])) {
                        $seen[$id] = true;
                        $next[] = $id;
                    }
                }
            }
            $frontier = $next;
        }

        return array_keys($seen);
    }

    /**
     * Ajoute les hôtes physiques des VMs (récursivement).
     *
     * @param  Collection<int, Server>  $servers
     * @return Collection<int, Server>
     */
    private function withHosts(Collection $servers): Collection
    {
        $all = $servers->keyBy('id');
        $missing = $servers->pluck('host_id')->filter()->diff($all->keys());
        while ($missing->isNotEmpty()) {
            $hosts = Server::with(['os', 'primaryIp'])->whereIn('id', $missing)->get()->keyBy('id');
            $all = $all->union($hosts);
            $missing = $hosts->pluck('host_id')->filter()->diff($all->keys());
        }

        return $all;
    }

    /**
     * @param  Collection<int, Application>  $apps
     * @param  Collection<int, Server>  $servers
     * @param  Collection<int, ApplicationFlow>  $flows
     * @param  Collection<int, Deployment>  $deployments
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>, truncated: bool}
     */
    private function assemble(Collection $apps, Collection $servers, Collection $flows, Collection $deployments): array
    {
        $truncated = $apps->count() + $servers->count() > self::MAX_NODES;
        if ($truncated) {
            return ['nodes' => [], 'edges' => [], 'truncated' => true];
        }

        $nodes = [];
        foreach ($apps as $a) {
            $nodes[] = [
                'id' => "app-{$a->id}",
                'type' => 'application',
                'data' => [
                    'id' => $a->id,
                    'label' => $a->name,
                    'code' => $a->code,
                    'kind' => $a->type->value,
                    'kindLabel' => $a->type->label(),
                    'criticality' => $a->criticality?->value,
                    'criticalityLabel' => $a->criticality?->label(),
                    'lifecycle' => $a->lifecycle_status->value,
                    'obsolete' => $a->technologies->contains(fn ($t) => $t->isObsolete()),
                    'personalData' => $a->handles_personal_data,
                ],
            ];
        }
        foreach ($servers as $s) {
            $nodes[] = [
                'id' => "srv-{$s->id}",
                'type' => 'server',
                'data' => [
                    'id' => $s->id,
                    'label' => $s->hostname,
                    'ip' => $s->primaryIp?->address,
                    'kind' => $s->kind->value,
                    'kindLabel' => $s->kind->label(),
                    'status' => $s->status->value,
                    'criticality' => $s->criticality?->value,
                    'os' => $s->os?->label(),
                    'obsolete' => (bool) $s->os?->isObsolete(),
                ],
            ];
        }

        $edges = [];
        foreach ($flows as $f) {
            $edges[] = [
                'id' => "flow-{$f->id}",
                'source' => "app-{$f->source_application_id}",
                'target' => "app-{$f->target_application_id}",
                'kind' => 'flow',
                'data' => [
                    'id' => $f->id,
                    'protocol' => $f->protocol->label(),
                    'port' => $f->port,
                    'encrypted' => $f->is_encrypted,
                    'critical' => $f->is_critical,
                ],
            ];
        }
        $serverIds = $servers->pluck('id')->flip();
        foreach ($deployments as $d) {
            if ($serverIds->has($d->server_id)) {
                $edges[] = [
                    'id' => "dep-{$d->id}",
                    'source' => "app-{$d->application_id}",
                    'target' => "srv-{$d->server_id}",
                    'kind' => 'deployment',
                    'data' => ['role' => $d->role->label()],
                ];
            }
        }
        foreach ($servers as $s) {
            if ($s->host_id && $serverIds->has($s->host_id)) {
                $edges[] = [
                    'id' => "host-{$s->id}",
                    'source' => "srv-{$s->id}",
                    'target' => "srv-{$s->host_id}",
                    'kind' => 'hosting',
                    'data' => [],
                ];
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges, 'truncated' => false];
    }
}
