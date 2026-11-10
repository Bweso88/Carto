<?php

namespace App\Http\Controllers\Cmdb;

use App\Http\Controllers\Controller;
use App\Services\Import\ApplicationSheetImporter;
use App\Support\Csv;
use App\Support\Spreadsheet\SpreadsheetReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import d'applications depuis Excel/CSV : dépôt du fichier -> aperçu (rien n'est écrit) -> confirmation.
 * L'option « remplacer » (vider les applications avant import) est réservée aux administrateurs.
 */
class ApplicationImportController extends Controller
{
    private const SESSION_KEY = 'application_import';

    public function create(Request $request): Response
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        return Inertia::render('applications/Import', [
            'pending' => $pending ? [
                'token' => $pending['token'],
                'file' => $pending['file'],
                'plan' => $pending['plan'],
            ] : null,
            'canReplace' => Gate::allows('manage-users'),
        ]);
    }

    public function preview(Request $request, ApplicationSheetImporter $importer): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'extensions:xlsx,xls,csv,txt,htm,html'],
            'replace' => ['boolean'],
        ], [
            'file.extensions' => 'Le fichier doit être un classeur Excel (.xlsx, .xls) ou un fichier CSV.',
        ], ['file' => 'fichier']);

        $replace = (bool) ($validated['replace'] ?? false);
        if ($replace) {
            Gate::authorize('manage-users');
        }

        $file = $validated['file'];
        try {
            $plan = $importer->analyze(SpreadsheetReader::read($file->getRealPath(), $file->getClientOriginalName()), $replace);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        $request->session()->put(self::SESSION_KEY, [
            'token' => (string) Str::uuid(),
            'file' => $file->getClientOriginalName(),
            'plan' => $plan,
        ]);

        return to_route('applications.import');
    }

    public function store(Request $request, ApplicationSheetImporter $importer): RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (! $pending || $request->input('token') !== $pending['token']) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Aperçu expiré : déposez le fichier à nouveau.']);

            return to_route('applications.import');
        }
        if ($pending['plan']['replace']) {
            Gate::authorize('manage-users');
        }

        $done = $importer->apply($pending['plan']);
        $request->session()->forget(self::SESSION_KEY);

        $parts = array_filter([
            $done['deleted'] ? "{$done['deleted']} ancienne(s) supprimée(s)" : null,
            $done['create'] ? "{$done['create']} créée(s)" : null,
            $done['update'] ? "{$done['update']} mise(s) à jour" : null,
            $done['deployments'] ? "{$done['deployments']} déploiement(s)" : null,
            $done['flows'] ? "{$done['flows']} dépendance(s)" : null,
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Import terminé : '.($parts ? implode(', ', $parts) : 'aucun changement').'.']);

        return to_route('applications.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return to_route('applications.import');
    }

    /** Modèle de fichier à remplir (CSV ouvrable dans Excel), mêmes colonnes que l'export Kerberos. */
    public function template(): StreamedResponse
    {
        return Csv::download('modele_import_applications.csv',
            ['Application', 'Serveur', 'Éditeur', 'Dépendance', 'Interaction', 'Activité', 'BDD', 'Langage', 'Utilisateur', 'App/Service'],
            [
                ['Portail RH', 'srv-exemple-01', 'Interne', 'Annuaire', 'Interactif', 'Metier', 'SQL Server', 'C#', 'DRH/DAF', 'IIS'],
                ['Annuaire', 'srv-exemple-02', 'Externe', '', 'Autonome', 'Support', '', '', 'ALL', ''],
            ]);
    }
}
