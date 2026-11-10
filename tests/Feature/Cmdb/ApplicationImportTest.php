<?php

namespace Tests\Feature\Cmdb;

use App\Enums\ApplicationType;
use App\Models\Application;
use App\Models\ApplicationFlow;
use App\Models\BusinessDomain;
use App\Models\Server;
use App\Models\User;
use App\Services\Import\ApplicationSheetImporter;
use App\Support\Spreadsheet\SpreadsheetReader;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApplicationImportTest extends TestCase
{
    use RefreshDatabase;

    /** Données fictives, au format de l'export « .xls » (tableau HTML) de Kerberos */
    private const SHEET = [
        ['ID', 'Application', 'Serveur', 'Éditeur', 'Dépendance', 'Interaction', 'Activité', 'BDD', 'Langage', 'Utilisateur', 'App/Service'],
        ['12', 'Portail Client', 'web-01', 'Interne', 'Coeur Bancaire', 'Interactif', 'Metier', 'SQL Server', 'C#', 'DCOM/DAF', 'IIS'],
        ['11', 'Messagerie', 'mail-01', 'Externe', 'N/A', 'Autonome', 'Metier', 'Editeur', 'Editeur', 'DSI', ''],
        ['10', 'Messagerie', 'mail-02', 'Externe', 'N/A', 'Autonome', 'Metier', 'Editeur', 'Editeur', 'DSI', ''],
        ['9', 'Coeur Bancaire prod', 'cbs-01', 'Externe', 'N/A', 'Interactif', 'Metier', 'Oracle', 'Genero', 'All_Core Banking', 'Apache'],
        ['8', 'Coeur Bancaire test', 'cbs-test', 'Externe', 'N/A', 'Interactif', 'Metier', 'Oracle', 'Genero', 'ALL', 'Apache'],
        ['7', 'Gestion des paies', 'DATA01', 'Interne', 'Comptabilité', 'Interactif', 'Metier', 'SQL Server', 'JAVA', 'DSI_RH', 'GLASSFISH'],
        ['6', 'Supervision', 'web-01', 'Externe', 'N/A', 'Autonome', 'Support', 'SANS', 'Editeur', 'DSI', 'DCOM'],
        ['5', 'ContrÃ´le Interne', 'web-01', 'Interne', 'Gestion de paie', 'Interactif', 'Management', 'SQL Server', 'Visual Basic', 'ALL', ''],
        ['', '', 'web-01', '', '', '', '', '', '', '', ''],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceSeeder::class);
        foreach (['web-01', 'mail-01', 'mail-02', 'cbs-01', 'Srv-Data01'] as $hostname) {
            Server::factory()->create(['hostname' => $hostname]);
        }
    }

    private function htmlXls(array $rows = self::SHEET): string
    {
        $html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body><table border="1"><thead><tr>';
        $html .= implode('', array_map(fn ($h) => '<th style="background:#333">'.htmlspecialchars($h).'</th>', $rows[0])).'</tr></thead><tbody>';
        foreach (array_slice($rows, 1) as $row) {
            $html .= '<tr>'.implode('', array_map(fn ($c) => '<td>'.htmlspecialchars((string) $c).'</td>', $row)).'</tr>';
        }
        $path = tempnam(sys_get_temp_dir(), 'xls').'.xls';
        file_put_contents($path, $html.'</tbody></table></body></html>');

        return $path;
    }

    private function analyze(bool $replace = false): array
    {
        return app(ApplicationSheetImporter::class)->analyze(SpreadsheetReader::read($this->htmlXls()), $replace);
    }

    public function test_reader_reads_html_xls_exports_and_repairs_encoding(): void
    {
        $rows = SpreadsheetReader::read($this->htmlXls());

        $this->assertSame('Éditeur', $rows[0][3]);
        $this->assertSame('Contrôle Interne', $rows[8][1]);
        $this->assertCount(10, $rows);
    }

    public function test_analysis_groups_rows_and_resolves_references_without_writing(): void
    {
        $plan = $this->analyze();
        $rows = collect($plan['rows'])->keyBy('name');

        $this->assertSame(0, Application::count());
        $this->assertSame(['create' => 8, 'update' => 0, 'unchanged' => 0, 'error' => 1], $plan['summary']);

        // Deux lignes « Messagerie » -> une application sur deux serveurs
        $this->assertSame(['mail-01', 'mail-02'], $rows['Messagerie']['data']['servers']);
        $this->assertSame(10, $rows['Messagerie']['data']['kerberos_id']);

        // Utilisateurs -> domaines métier
        $this->assertSame(['DCOM', 'DAF'], $rows['Portail Client']['data']['domains']);
        $this->assertSame(['DSI'], $rows['Gestion des paies']['data']['domains']);
        $this->assertSame('Tous (Core Banking)', $rows['Coeur Bancaire prod']['data']['user_base']);
        $this->assertSame([], $rows['Coeur Bancaire test']['data']['domains']);

        // Technologies (« Editeur », « SANS » ignorés ; colonne App/Service filtrée)
        $this->assertSame(['SQL Server', 'C#', 'IIS'], array_column($rows['Portail Client']['data']['technologies'], 'name'));
        $this->assertSame(['Oracle Database', 'Genero', 'Apache HTTP Server'], array_column($rows['Coeur Bancaire prod']['data']['technologies'], 'name'));
        $this->assertSame([], $rows['Supervision']['data']['technologies']);

        // Dépendances : version « prod », pluriel, application absente à créer
        $this->assertSame('Coeur Bancaire prod', $rows['Portail Client']['data']['dependencies'][0]['target']);
        $this->assertSame('Gestion des paies', $rows['Contrôle Interne']['data']['dependencies'][0]['target']);
        $this->assertTrue($rows['Comptabilité']['auto']);

        // Serveur au nom approchant, serveur absent
        $this->assertSame(['Srv-Data01'], $rows['Gestion des paies']['data']['servers']);
        $this->assertSame(['cbs-test' => 1], $plan['missing_servers']);
        $this->assertSame(ApplicationType::Technical->value, $rows['Supervision']['data']['type']);
    }

    public function test_replace_mode_empties_applications_before_importing(): void
    {
        $old = Application::factory()->create(['name' => 'Ancienne']);
        $other = Application::factory()->create();
        $old->deployments()->create(['server_id' => Server::first()->id]);
        ApplicationFlow::factory()->create(['source_application_id' => $old->id, 'target_application_id' => $other->id]);
        $other->delete();

        $plan = $this->analyze(replace: true);
        $this->assertSame(['applications' => 2, 'deployments' => 1, 'flows' => 1], $plan['existing']);

        $done = app(ApplicationSheetImporter::class)->apply($plan);

        $this->assertSame(['deleted' => 2, 'create' => 8, 'update' => 0, 'deployments' => 7, 'flows' => 3], $done);
        $this->assertSame(0, Application::withTrashed()->where('name', 'Ancienne')->count());
        $this->assertSame(5, Server::count());

        $portail = Application::where('name', 'Portail Client')->firstOrFail();
        $this->assertSame('APP-0001', $portail->code);
        $this->assertSame(12, $portail->kerberos_id);
        $this->assertEqualsCanonicalizing(['DAF', 'DCOM'], $portail->businessDomains->pluck('name')->all());
        $this->assertSame(['Coeur Bancaire prod'], $portail->dependencies->pluck('name')->all());
        $this->assertStringContainsString('Développement interne', $portail->description);
        $this->assertSame(2, Application::where('name', 'Messagerie')->sole()->deployments()->count());
        $this->assertSame(['web-01'], Application::where('name', 'Contrôle Interne')->sole()->servers->pluck('hostname')->all());
    }

    public function test_reimporting_without_replace_changes_nothing(): void
    {
        $importer = app(ApplicationSheetImporter::class);
        $importer->apply($this->analyze());

        $again = $this->analyze();

        $this->assertSame(['create' => 0, 'update' => 0, 'unchanged' => 7, 'error' => 1], $again['summary']);
        $this->assertSame(['deleted' => 0, 'create' => 0, 'update' => 0, 'deployments' => 0, 'flows' => 0], $importer->apply($again));
        $this->assertSame(8, Application::count());
    }

    public function test_existing_domains_are_reused(): void
    {
        BusinessDomain::create(['name' => 'DAF - Direction financière']);

        $rows = collect($this->analyze()['rows'])->keyBy('name');

        $this->assertSame(['DCOM', 'DAF - Direction financière'], $rows['Portail Client']['data']['domains']);
    }

    public function test_screen_flow_for_editors(): void
    {
        $editor = User::factory()->editor()->create();
        $file = new UploadedFile($this->htmlXls(), 'export_20261110.xls', null, null, true);

        $this->actingAs($editor)->get(route('applications.import'))
            ->assertInertia(fn (Assert $page) => $page->component('applications/Import')->where('canReplace', false));

        // Un contributeur ne peut pas vider la table
        $this->actingAs($editor)->post(route('applications.import.preview'), ['file' => $file, 'replace' => true])->assertForbidden();

        $this->actingAs($editor)->post(route('applications.import.preview'), ['file' => $file])
            ->assertRedirect(route('applications.import'));
        $token = session('application_import.token');

        $this->actingAs($editor)->post(route('applications.import.store'), ['token' => $token])
            ->assertRedirect(route('applications.index'));
        $this->assertSame(8, Application::count());
    }

    public function test_admins_can_replace_and_readers_cannot_import(): void
    {
        Application::factory()->count(3)->create();
        $admin = User::factory()->admin()->create();
        $file = new UploadedFile($this->htmlXls(), 'export.xls', null, null, true);

        $this->actingAs(User::factory()->create())->get(route('applications.import'))->assertForbidden();

        $this->actingAs($admin)->post(route('applications.import.preview'), ['file' => $file, 'replace' => true])->assertRedirect();
        $this->actingAs($admin)->get(route('applications.import'))
            ->assertInertia(fn (Assert $page) => $page->where('pending.plan.replace', true)->where('pending.plan.existing.applications', 3));
        $this->actingAs($admin)->post(route('applications.import.store'), ['token' => session('application_import.token')])->assertRedirect();

        $this->assertSame(8, Application::count());
        $this->assertSame('APP-0001', Application::min('code'));
    }

    public function test_command_supports_dry_run(): void
    {
        $this->artisan('merimap:import-applications', ['file' => $this->htmlXls(), '--dry-run' => true])
            ->expectsOutputToContain('Simulation')
            ->assertSuccessful();
        $this->assertSame(0, Application::count());

        $this->artisan('merimap:import-applications', ['file' => $this->htmlXls()])->assertFailed(); // une ligne en erreur
        $this->assertSame(8, Application::count());
    }
}
