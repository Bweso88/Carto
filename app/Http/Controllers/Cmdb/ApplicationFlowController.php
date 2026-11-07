<?php

namespace App\Http\Controllers\Cmdb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cmdb\FlowRequest;
use App\Models\Application;
use App\Models\ApplicationFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ApplicationFlowController extends Controller
{
    public function store(FlowRequest $request, Application $application): RedirectResponse
    {
        $data = $request->validated();
        [$source, $target] = $data['direction'] === 'outgoing'
            ? [$application->id, (int) $data['other_application_id']]
            : [(int) $data['other_application_id'], $application->id];

        $exists = ApplicationFlow::where(['source_application_id' => $source, 'target_application_id' => $target, 'protocol' => $data['protocol']])->exists();
        if ($exists) {
            throw ValidationException::withMessages(['other_application_id' => 'Ce flux existe déjà.']);
        }

        ApplicationFlow::create([
            'source_application_id' => $source,
            'target_application_id' => $target,
            'protocol' => $data['protocol'],
            'port' => $data['port'] ?? null,
            'is_encrypted' => $data['is_encrypted'] ?? null,
            'is_critical' => $data['is_critical'] ?? true,
            'frequency' => $data['frequency'] ?? null,
            'description' => $data['description'] ?? null,
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Flux ajouté.']);

        return back();
    }

    public function destroy(ApplicationFlow $flow): RedirectResponse
    {
        Gate::authorize('edit-cmdb');
        $flow->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Flux supprimé.']);

        return back();
    }
}
