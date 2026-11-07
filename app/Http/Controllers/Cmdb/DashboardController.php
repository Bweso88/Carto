<?php

namespace App\Http\Controllers\Cmdb;

use App\Enums\Criticality;
use App\Enums\ServerKind;
use App\Enums\ServerStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationFlow;
use App\Models\Server;
use App\Models\Technology;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $obsoleteTechIds = Technology::unsupportedBefore(now())->pluck('id');
        $soonTechIds = Technology::unsupportedBefore(now()->addMonths(6))->whereNotIn('id', $obsoleteTechIds)->pluck('id');

        $activeServers = Server::where('status', '!=', ServerStatus::Decommissioned);

        return Inertia::render('Dashboard', [
            'kpis' => [
                'applications' => Application::count(),
                'critical_applications' => Application::where('criticality', Criticality::Critical)->count(),
                'servers' => (clone $activeServers)->count(),
                'physical_servers' => (clone $activeServers)->where('kind', ServerKind::Physical)->count(),
                'virtual_servers' => (clone $activeServers)->where('kind', ServerKind::Virtual)->count(),
                'flows' => ApplicationFlow::count(),
            ],
            'alerts' => [
                'servers_obsolete_os' => (clone $activeServers)->whereIn('os_id', $obsoleteTechIds)->count(),
                'servers_os_ending_soon' => (clone $activeServers)->whereIn('os_id', $soonTechIds)->count(),
                'applications_obsolete_stack' => Application::whereHas('technologies', fn (Builder $q) => $q->whereIn('technologies.id', $obsoleteTechIds))->count(),
                'unencrypted_flows' => ApplicationFlow::where('is_encrypted', false)->count(),
                'applications_without_owner' => Application::whereNull('technical_owner_team_id')->count(),
                'applications_without_servers' => Application::doesntHave('deployments')->count(),
                'servers_without_criticality' => (clone $activeServers)->whereNull('criticality')->count(),
            ],
            'criticalApplications' => Application::where('criticality', Criticality::Critical)
                ->withCount(['deployments', 'incomingFlows'])
                ->orderByDesc('incoming_flows_count')->limit(8)->get()
                ->map(fn (Application $a) => [
                    'id' => $a->id, 'code' => $a->code, 'name' => $a->name,
                    'servers_count' => $a->deployments_count, 'dependents_count' => $a->incoming_flows_count,
                ]),
            'obsoleteServers' => (clone $activeServers)->whereIn('os_id', $obsoleteTechIds)->with('os')->orderBy('hostname')->limit(8)->get()
                ->map(fn (Server $s) => ['id' => $s->id, 'hostname' => $s->hostname, 'os' => $s->os->label(), 'since' => ($s->os->end_of_life_at ?? $s->os->end_of_support_at)->toDateString()]),
        ]);
    }
}
