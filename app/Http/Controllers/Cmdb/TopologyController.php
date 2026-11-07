<?php

namespace App\Http\Controllers\Cmdb;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Environment;
use App\Services\Topology\TopologyGraph;
use App\Support\CmdbOptions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TopologyController extends Controller
{
    public function __invoke(Request $request, TopologyGraph $graph): Response
    {
        $validated = $request->validate([
            'environment' => ['nullable', 'integer', 'exists:environments,id'],
            'domain' => ['nullable', 'integer', 'exists:business_domains,id'],
            'focus' => ['nullable', 'integer', 'exists:applications,id'],
            'depth' => ['nullable', 'integer', 'between:1,5'],
            'servers' => ['nullable', 'in:0,1'],
        ]);

        $filters = [
            // Par défaut : production
            'environment' => $validated['environment'] ?? Environment::where('is_production', true)->orderBy('sort_order')->value('id'),
            'domain' => $validated['domain'] ?? null,
            'focus' => $validated['focus'] ?? null,
            'depth' => $validated['depth'] ?? 2,
            'servers' => ($validated['servers'] ?? '1') === '1',
        ];

        return Inertia::render('topology/Index', [
            'graph' => $graph->build($filters),
            'filters' => [
                ...$filters,
                'environment' => $filters['environment'] ? (string) $filters['environment'] : null,
                'domain' => $filters['domain'] ? (string) $filters['domain'] : null,
                'focus' => $filters['focus'] ? (string) $filters['focus'] : null,
                'depth' => (string) $filters['depth'],
            ],
            'options' => [
                'environments' => CmdbOptions::environments(),
                'businessDomains' => CmdbOptions::businessDomains(),
                'applications' => Application::orderBy('name')->get(['id', 'code', 'name'])
                    ->map(fn ($a) => ['value' => (string) $a->id, 'label' => "{$a->name} ({$a->code})"])->all(),
            ],
        ]);
    }
}
