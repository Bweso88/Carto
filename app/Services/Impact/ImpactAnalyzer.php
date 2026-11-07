<?php

namespace App\Services\Impact;

use App\Models\Application;
use App\Models\ApplicationFlow;
use App\Models\Deployment;
use App\Models\Server;
use Illuminate\Support\Collection;

/**
 * Simule une panne (serveurs et/ou applications) et calcule sa propagation
 * (« blast radius ») le long des déploiements puis des flux applicatifs.
 *
 * Règles :
 *  1. La panne d'un hôte physique entraîne celle de toutes ses VMs (récursivement).
 *  2. Pour chaque rôle de déploiement (web, base, applicatif…) d'une application :
 *     tous les serveurs du rôle en panne => application DOWN ;
 *     une partie seulement => DEGRADED (la redondance tient).
 *  3. Une application en panne directe (simulation applicative) est DOWN.
 *  4. Une application DOWN rend ses consommateurs DOWN via un flux critique,
 *     DEGRADED via un flux non critique. Une application DEGRADED rend ses
 *     consommateurs DEGRADED.
 */
final class ImpactAnalyzer
{
    /**
     * @param  iterable<Server>|Server  $servers
     * @param  bool  $productionOnly  ne considérer que les déploiements de production
     */
    public function simulateServerFailure(iterable|Server $servers, bool $productionOnly = true): ImpactReport
    {
        return $this->simulate($servers instanceof Server ? [$servers] : $servers, [], $productionOnly);
    }

    /**
     * @param  iterable<Application>|Application  $applications
     */
    public function simulateApplicationFailure(iterable|Application $applications): ImpactReport
    {
        return $this->simulate([], $applications instanceof Application ? [$applications] : $applications);
    }

    /**
     * Panne combinée de serveurs et d'applications.
     *
     * @param  iterable<Server>  $servers
     * @param  iterable<Application>  $applications
     */
    public function simulate(iterable $servers, iterable $applications, bool $productionOnly = true): ImpactReport
    {
        $failed = $this->expandToGuests(collect($servers));
        $failedIds = $failed->pluck('id')->flip();

        /** @var Collection<int, ImpactedApplication> $impacted */
        $impacted = collect();

        foreach ($applications as $app) {
            $impacted->put($app->id, new ImpactedApplication($app, ImpactStatus::Down, 0, null, true));
        }

        // Impact direct via les déploiements
        if ($failedIds->isNotEmpty()) {
            $deployments = Deployment::query()
                ->with(['application', 'environment'])
                ->whereIn('application_id', Deployment::query()->whereIn('server_id', $failedIds->keys())->select('application_id'))
                ->get()
                ->filter(fn (Deployment $d) => $d->application !== null)
                ->filter(fn (Deployment $d) => ! $productionOnly || $d->environment === null || $d->environment->is_production);

            foreach ($deployments->groupBy('application_id') as $appDeployments) {
                $status = null;
                foreach ($appDeployments->groupBy(fn (Deployment $d) => $d->role->value) as $roleDeployments) {
                    $down = $roleDeployments->filter(fn (Deployment $d) => $failedIds->has($d->server_id))->count();
                    if ($down === 0) {
                        continue;
                    }
                    $roleStatus = $down === $roleDeployments->count() ? ImpactStatus::Down : ImpactStatus::Degraded;
                    $status = $status ? $status->worst($roleStatus) : $roleStatus;
                }
                if ($status) {
                    $app = $appDeployments->first()->application;
                    $existing = $impacted->get($app->id);
                    if ($existing === null) {
                        $impacted->put($app->id, new ImpactedApplication($app, $status, 0));
                    } else {
                        $existing->status = $existing->status->worst($status);
                    }
                }
            }
        }

        $this->propagate($impacted);

        return new ImpactReport($failed->values(), $impacted);
    }

    /**
     * Propagation le long des flux (parcours en largeur).
     *
     * @param  Collection<int, ImpactedApplication>  $impacted
     */
    private function propagate(Collection $impacted): void
    {
        $queue = $impacted->keys()->all();
        while ($queue) {
            $current = $impacted->get(array_shift($queue));
            $flows = ApplicationFlow::query()
                ->with('source')
                ->where('target_application_id', $current->application->id)
                ->get()
                ->filter(fn (ApplicationFlow $f) => $f->source !== null);

            foreach ($flows as $flow) {
                $propagated = $current->status === ImpactStatus::Down && $flow->is_critical
                    ? ImpactStatus::Down
                    : ImpactStatus::Degraded;
                $existing = $impacted->get($flow->source_application_id);

                if ($existing === null) {
                    $impacted->put($flow->source_application_id, new ImpactedApplication(
                        $flow->source, $propagated, $current->depth + 1, $current->application,
                    ));
                    $queue[] = $flow->source_application_id;
                } elseif ($existing->status === ImpactStatus::Degraded && $propagated === ImpactStatus::Down) {
                    // Aggravation : on repropage le nouvel état
                    $existing->status = ImpactStatus::Down;
                    $existing->via = $current->application;
                    $existing->depth = $current->depth + 1;
                    $queue[] = $flow->source_application_id;
                }
            }
        }
    }

    /**
     * @param  Collection<int, Server>  $servers
     * @return Collection<int, Server>
     */
    private function expandToGuests(Collection $servers): Collection
    {
        $all = $servers->keyBy('id');
        $frontier = $servers->pluck('id')->all();

        while ($frontier) {
            $guests = Server::query()->whereIn('host_id', $frontier)->whereNotIn('id', $all->keys())->get();
            $frontier = $guests->pluck('id')->all();
            $all = $all->union($guests->keyBy('id'));
        }

        return $all;
    }
}
