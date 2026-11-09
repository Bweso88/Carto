<?php

namespace App\Http\Controllers\Cmdb;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Services\Import\ServerSheetImporter;
use App\Support\Csv;
use App\Support\Spreadsheet\SpreadsheetReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import de serveurs depuis Excel/CSV : dépôt du fichier -> aperçu (rien n'est écrit) -> confirmation.
 * Le plan d'import est conservé en session entre l'aperçu et la confirmation.
 */
class ServerImportController extends Controller
{
    private const SESSION_KEY = 'server_import';

    public function create(Request $request): Response
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        return Inertia::render('servers/Import', [
            'pending' => $pending ? [
                'token' => $pending['token'],
                'file' => $pending['file'],
                'environment' => $pending['environment'],
                'plan' => $pending['plan'],
            ] : null,
            'options' => ['environments' => Environment::orderBy('sort_order')->get()
                ->map(fn (Environment $e) => ['value' => $e->code, 'label' => $e->name])],
        ]);
    }

    public function preview(Request $request, ServerSheetImporter $importer): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv,txt'],
            'environment' => ['required', 'exists:environments,code'],
        ], [
            'file.mimes' => 'Le fichier doit être un classeur Excel (.xlsx) ou un fichier CSV.',
        ], ['file' => 'fichier']);

        $file = $validated['file'];
        try {
            $plan = $importer->analyze(SpreadsheetReader::read($file->getRealPath(), $file->getClientOriginalName()), $validated['environment']);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        $request->session()->put(self::SESSION_KEY, [
            'token' => (string) Str::uuid(),
            'file' => $file->getClientOriginalName(),
            'environment' => $validated['environment'],
            'plan' => $plan,
        ]);

        return to_route('servers.import');
    }

    public function store(Request $request, ServerSheetImporter $importer): RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (! $pending || $request->input('token') !== $pending['token']) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Aperçu expiré : déposez le fichier à nouveau.']);

            return to_route('servers.import');
        }

        $done = $importer->apply($pending['plan']);
        $request->session()->forget(self::SESSION_KEY);

        $parts = array_filter([
            $done['create'] ? "{$done['create']} créé(s)" : null,
            $done['update'] ? "{$done['update']} mis à jour" : null,
            $done['restore'] ? "{$done['restore']} restauré(s)" : null,
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Import terminé : '.($parts ? implode(', ', $parts) : 'aucun changement').'.']);

        return to_route('servers.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return to_route('servers.import');
    }

    /** Modèle de fichier à remplir (CSV ouvrable dans Excel). */
    public function template(): StreamedResponse
    {
        return Csv::download('modele_import_serveurs.csv',
            ['Serveur', 'IP', 'OS', 'Type', 'Localisation', 'Criticité', 'Environnement', 'Hôte', 'Description'],
            [
                ['srv-exemple-01', '10.10.0.10', 'WS 2022', 'Virtuel', 'Site primaire', 'Critique', 'PROD', 'hyperviseur-01', 'Serveur applicatif ERP'],
                ['hyperviseur-01', '10.10.0.2', 'Linux Rhel9', 'Physique', 'Site primaire', 'À surveiller', 'PROD', '', ''],
            ]);
    }
}
