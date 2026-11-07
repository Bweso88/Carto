<?php

namespace App\Http\Controllers\Cmdb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cmdb\ApplicationRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
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

class ApplicationController extends Controller
{
    private const SORTS = ['name' => 'name', 'code' => 'code', 'criticality' => 'criticality', 'type' => 'type', 'updated' => 'updated_at'];

    public function index(Request $request): Response
    {
        $applications = $this->filteredQuery($request)
            ->with(['businessDomain', 'technicalOwner'])
            ->withCount(['deployments', 'outgoingFlows', 'incomingFlows'])
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Application $a) => ApplicationResource::make($a)->resolve());

        return Inertia::render('applications/Index', [
            'applications' => $applications,
            'filters' => $this->filters($request),
            'options' => [
                ...CmdbOptions::enums(),
                'businessDomains' => CmdbOptions::businessDomains(),
                'teams' => CmdbOptions::teams(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filteredQuery($request)
            ->with(['businessDomain', 'vendor', 'technicalOwner', 'businessOwner', 'technologies', 'servers'])
            ->lazy()
            ->map(fn (Application $a) => [
                $a->code, $a->name, $a->type->label(), $a->lifecycle_status->label(), $a->criticality?->label(),
                $a->businessDomain?->name, $a->vendor?->name, $a->businessOwner?->name, $a->technicalOwner?->name,
                $a->servers->pluck('hostname')->unique()->implode(' '), $a->technologies->map->label()->implode(', '),
                $a->sla_target, $a->handles_personal_data ? 'Oui' : 'Non', $a->user_base, $a->description,
            ]);

        return Csv::download('applications_'.now()->format('Ymd_His').'.csv',
            ['Code', 'Application', 'Type', 'Cycle de vie', 'Criticité', 'Domaine', 'Éditeur', 'Resp. métier', 'Resp. technique',
                'Serveurs', 'Technologies', 'SLA (%)', 'Données personnelles', 'Utilisateurs', 'Description'],
            $rows);
    }

    public function create(): Response
    {
        return Inertia::render('applications/Form', [
            'application' => null,
            'values' => ['type' => 'business', 'lifecycle_status' => 'production', 'handles_personal_data' => false, 'technology_ids' => []],
            'options' => $this->formOptions(),
        ]);
    }

    public function store(ApplicationRequest $request): RedirectResponse
    {
        $application = DB::transaction(function () use ($request) {
            $application = Application::create($request->safe()->except('technology_ids'));
            $application->technologies()->sync($request->validated('technology_ids', []));

            return $application;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Application {$application->name} créée ({$application->code})."]);

        return to_route('applications.show', $application);
    }

    public function show(Application $application): Response
    {
        $application->load([
            'businessDomain', 'vendor', 'businessOwner', 'technicalOwner', 'technologies',
            'deployments' => fn ($q) => $q->with(['server.primaryIp', 'server.os', 'environment']),
            'outgoingFlows.target', 'incomingFlows.source',
        ]);

        return Inertia::render('applications/Show', [
            'application' => ApplicationResource::make($application)->resolve(),
            'options' => [
                ...CmdbOptions::enums(),
                'environments' => CmdbOptions::environments(),
                'servers' => Server::orderBy('hostname')->get(['id', 'hostname'])
                    ->map(fn ($s) => ['value' => (string) $s->id, 'label' => $s->hostname])->all(),
                'applications' => Application::whereKeyNot($application->id)->orderBy('name')->get(['id', 'code', 'name'])
                    ->map(fn ($a) => ['value' => (string) $a->id, 'label' => "{$a->name} ({$a->code})"])->all(),
            ],
        ]);
    }

    public function edit(Application $application): Response
    {
        return Inertia::render('applications/Form', [
            'application' => ['id' => $application->id, 'code' => $application->code, 'name' => $application->name],
            'values' => [
                ...$application->only([
                    'name', 'description', 'handles_personal_data', 'business_domain_id', 'vendor_id',
                    'business_owner_team_id', 'technical_owner_team_id', 'user_base', 'user_count',
                    'rto_minutes', 'rpo_minutes', 'documentation_url',
                ]),
                'type' => $application->type->value,
                'lifecycle_status' => $application->lifecycle_status->value,
                'criticality' => $application->criticality?->value,
                'data_classification' => $application->data_classification?->value,
                'sla_target' => $application->sla_target !== null ? (float) $application->sla_target : null,
                'technology_ids' => $application->technologies()->pluck('technologies.id')->all(),
            ],
            'options' => $this->formOptions(),
        ]);
    }

    public function update(ApplicationRequest $request, Application $application): RedirectResponse
    {
        DB::transaction(function () use ($request, $application) {
            $application->update($request->safe()->except('technology_ids'));
            $application->technologies()->sync($request->validated('technology_ids', []));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Application {$application->name} mise à jour."]);

        return to_route('applications.show', $application);
    }

    public function destroy(Application $application): RedirectResponse
    {
        $application->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => "Application {$application->name} supprimée."]);

        return to_route('applications.index');
    }

    // ----------------------------------------------------------------------

    /** @return array<string, string|null> */
    private function filters(Request $request): array
    {
        return $request->only(['search', 'type', 'lifecycle', 'criticality', 'domain', 'team', 'sort']);
    }

    /** @return Builder<Application> */
    private function filteredQuery(Request $request): Builder
    {
        $f = $this->filters($request);
        $sort = (string) ($f['sort'] ?? 'name');
        $column = self::SORTS[ltrim($sort, '-')] ?? 'name';

        return Application::query()
            ->search($f['search'] ?? null)
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($f['lifecycle'] ?? null, fn ($q, $v) => $q->where('lifecycle_status', $v))
            ->when($f['criticality'] ?? null, fn ($q, $v) => $v === 'none' ? $q->whereNull('criticality') : $q->where('criticality', $v))
            ->when($f['domain'] ?? null, fn ($q, $v) => $q->where('business_domain_id', $v))
            ->when($f['team'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('technical_owner_team_id', $v)->orWhere('business_owner_team_id', $v)))
            ->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderBy('id');
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            ...CmdbOptions::enums(),
            'businessDomains' => CmdbOptions::businessDomains(),
            'vendors' => CmdbOptions::vendors(),
            'teams' => CmdbOptions::teams(),
            'technologies' => CmdbOptions::stackTechnologies(),
        ];
    }
}
