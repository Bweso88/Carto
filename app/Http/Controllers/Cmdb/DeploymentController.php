<?php

namespace App\Http\Controllers\Cmdb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cmdb\DeploymentRequest;
use App\Models\Application;
use App\Models\Deployment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DeploymentController extends Controller
{
    public function store(DeploymentRequest $request, Application $application): RedirectResponse
    {
        $application->deployments()->create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Déploiement ajouté.']);

        return back();
    }

    public function destroy(Deployment $deployment): RedirectResponse
    {
        Gate::authorize('edit-cmdb');
        $deployment->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Déploiement retiré.']);

        return back();
    }
}
