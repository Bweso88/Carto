<?php

namespace App\Http\Controllers\Cmdb;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Server;
use App\Services\Impact\ImpactAnalyzer;
use App\Services\Topology\TopologyGraph;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Simulation de panne : GET /impact?servers[]=3&applications[]=5
 * Lecture seule (rien n'est modifié) : accessible à tous les utilisateurs connectés.
 */
class ImpactController extends Controller
{
    public function __invoke(Request $request, ImpactAnalyzer $analyzer, TopologyGraph $graph): Response
    {
        $validated = $request->validate([
            'servers' => ['nullable', 'array', 'max:20'],
            'servers.*' => ['integer', 'exists:servers,id'],
            'applications' => ['nullable', 'array', 'max:20'],
            'applications.*' => ['integer', 'exists:applications,id'],
            'all_environments' => ['nullable', 'in:0,1'],
        ]);

        $servers = Server::whereIn('id', $validated['servers'] ?? [])->get();
        $applications = Application::whereIn('id', $validated['applications'] ?? [])->get();
        $productionOnly = ($validated['all_environments'] ?? '0') !== '1';

        $report = null;
        $reportGraph = null;
        if ($servers->isNotEmpty() || $applications->isNotEmpty()) {
            $result = $analyzer->simulate($servers, $applications, $productionOnly);
            $report = $result->toArray();
            $reportGraph = $graph->forImpact($result);
        }

        return Inertia::render('impact/Index', [
            'selection' => [
                'servers' => $servers->map(fn ($s) => ['value' => (string) $s->id, 'label' => $s->hostname])->values(),
                'applications' => $applications->map(fn ($a) => ['value' => (string) $a->id, 'label' => "{$a->name} ({$a->code})"])->values(),
                'all_environments' => ! $productionOnly,
            ],
            'report' => $report,
            'graph' => $reportGraph,
            'options' => [
                'servers' => Server::orderBy('hostname')->get(['id', 'hostname', 'kind'])
                    ->map(fn ($s) => ['value' => (string) $s->id, 'label' => $s->hostname.' · '.$s->kind->label()])->all(),
                'applications' => Application::orderBy('name')->get(['id', 'code', 'name'])
                    ->map(fn ($a) => ['value' => (string) $a->id, 'label' => "{$a->name} ({$a->code})"])->all(),
            ],
        ]);
    }
}
