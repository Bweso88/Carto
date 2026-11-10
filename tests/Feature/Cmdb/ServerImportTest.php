<?php

namespace Tests\Feature\Cmdb;

use App\Enums\Criticality;
use App\Enums\ServerKind;
use App\Models\Server;
use App\Models\User;
use App\Services\Import\ServerSheetImporter;
use App\Support\Spreadsheet\SpreadsheetReader;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\XlsxBuilder;
use Tests\TestCase;

class ServerImportTest extends TestCase
{
    use RefreshDatabase;

    /** Données fictives, au format du fichier de la DSI */
    private const SHEET = [
        ['Serveur', 'IP', 'OS', 'Type', 'Localisation', 'Criticité'],
        ['DB-APP-01', '10.0.0.91', 'Linux Rhel9', 'Virtuel', 'Site primaire', 'Critique'],
        ['hyp01', '10.0.1.101', 'WS', 'Physique', 'Site secondaire', 'À surveiller'],
        ['MON-OLD', '10.0.7.50', 'WS 2012 R2', 'Physique', 'Salle serveur', 'Non défini'],
        ['paie-test', '10.0.0.103', 'WS', 'Virtuel', null, 'Critique'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceSeeder::class);
    }

    private function analyze(array $rows): array
    {
        return app(ServerSheetImporter::class)->analyze(SpreadsheetReader::read(XlsxBuilder::make($rows)));
    }

    public function test_reader_handles_xlsx_and_skips_empty_rows(): void
    {
        $rows = SpreadsheetReader::read(XlsxBuilder::make([['Serveur', 'IP'], [null, null], ['srv1', null], ['srv2', '10.0.0.2']]));

        $this->assertSame([['Serveur', 'IP'], ['srv1'], ['srv2', '10.0.0.2']], $rows);
    }

    public function test_reader_handles_excel_csv_in_windows_1252(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, mb_convert_encoding("Serveur;Criticité\r\nsrv1;À surveiller\r\n", 'Windows-1252', 'UTF-8'));

        $this->assertSame([['Serveur', 'Criticité'], ['srv1', 'À surveiller']], SpreadsheetReader::read($path, 'export.csv'));
    }

    public function test_reader_rejects_unknown_formats(): void
    {
        $this->expectExceptionMessage('Format non pris en charge');
        SpreadsheetReader::read(__FILE__, 'document.docx');
    }

    public function test_reader_explains_how_to_convert_binary_xls(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xls');
        file_put_contents($path, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 64));

        $this->expectExceptionMessage('enregistrez-le au format .xlsx');
        SpreadsheetReader::read($path, 'ancien.xls');
    }

    public function test_analysis_interprets_values_without_writing(): void
    {
        $plan = $this->analyze(self::SHEET);

        $this->assertSame(['create' => 4, 'update' => 0, 'restore' => 0, 'unchanged' => 0, 'error' => 0], $plan['summary']);
        $this->assertSame(0, Server::count(), "L'analyse n'écrit rien");

        [$db, $hyp, $old, $test] = $plan['rows'];
        $this->assertSame(['Red Hat Enterprise Linux 9', 'virtual', 'critical'], [$db['data']['os'], $db['data']['kind'], $db['data']['criticality']]);
        $this->assertSame(['Windows Server', 'physical', 'high'], [$hyp['data']['os'], $hyp['data']['kind'], $hyp['data']['criticality']]);
        $this->assertNull($old['data']['criticality'], '« Non défini » = criticité non évaluée');
        $this->assertContains('Windows Server 2012 R2 est en fin de vie.', $old['warnings']);
        $this->assertSame('RECETTE', $test['data']['environment'], 'Nom contenant « test » : environnement de recette');
        $this->assertSame('PROD', $db['data']['environment']);
    }

    public function test_apply_creates_servers_with_catalog_os_sites_and_ips(): void
    {
        $importer = app(ServerSheetImporter::class);
        $done = $importer->apply($this->analyze(self::SHEET));

        $this->assertSame(['create' => 4, 'update' => 0, 'restore' => 0], $done);
        $old = Server::firstWhere('hostname', 'MON-OLD');
        $this->assertSame(ServerKind::Physical, $old->kind);
        $this->assertSame('Windows Server 2012 R2', $old->os->label());
        $this->assertTrue($old->os->isObsolete(), "Rattaché à l'entrée du catalogue, avec sa date de fin de vie");
        $this->assertSame('Salle serveur', $old->site->name);
        $this->assertSame('10.0.7.50', $old->primaryIp->address);
        $this->assertSame(Criticality::Critical, Server::firstWhere('hostname', 'DB-APP-01')->criticality);
        $this->assertNull(Server::firstWhere('hostname', 'paie-test')->site_id);
    }

    public function test_reimport_is_idempotent_and_never_erases_with_empty_cells(): void
    {
        $importer = app(ServerSheetImporter::class);
        $importer->apply($this->analyze(self::SHEET));
        Server::firstWhere('hostname', 'hyp01')->update(['description' => 'Hyperviseur principal']);

        $again = $this->analyze(self::SHEET);
        $this->assertSame(4, $again['summary']['unchanged']);

        // Fichier partiel : nouvelle IP et nouvelle criticité, cellules vides ailleurs
        $partial = $this->analyze([['Nom', 'Criticité', 'Adresse IP'], ['HYP01', 'Critique', '10.0.1.250']]);
        $this->assertSame('update', $partial['rows'][0]['action']);
        $this->assertSame(['Criticité', 'Adresse IP'], array_column($partial['rows'][0]['changes'], 'field'));
        $importer->apply($partial);

        $hyp = Server::firstWhere('hostname', 'hyp01');
        $this->assertSame(Criticality::Critical, $hyp->criticality);
        $this->assertSame('Hyperviseur principal', $hyp->description, 'Pas de colonne Description : rien effacé');
        $this->assertSame('Windows Server', $hyp->os->name);
        $this->assertSame(['10.0.1.101', '10.0.1.250'], $hyp->ipAddresses()->orderBy('address')->pluck('address')->all());
        $this->assertSame('10.0.1.101', $hyp->primaryIp->address, "L'adresse principale ne change pas");
    }

    public function test_errors_and_warnings_are_reported(): void
    {
        $plan = $this->analyze([
            ['Serveur', 'IP', 'Type', 'Commentaire'],
            ['srv-a', '10.0.0.1 / 999.1.1.1', 'Ordinateur', 'note'],
            ['SRV-A', '10.0.0.2', null, null],
            ['nom invalide', null, null, null],
            [null, '10.0.0.4', null, null],
        ]);

        $this->assertSame(1, $plan['summary']['create']);
        $this->assertSame(3, $plan['summary']['error']);
        $this->assertSame(['10.0.0.1'], $plan['rows'][0]['data']['ips']);
        $this->assertContains('Adresse IP ignorée : « 999.1.1.1 ».', $plan['rows'][0]['warnings']);
        $this->assertStringContainsString('Type « Ordinateur » non reconnu', implode(' ', $plan['rows'][0]['warnings']));
        $this->assertStringContainsString('Doublon', $plan['rows'][1]['errors'][0]);
        $this->assertSame('note', $plan['rows'][0]['data']['description'], '« Commentaire » reconnu comme description');
    }

    public function test_host_column_links_vms_to_their_hypervisor(): void
    {
        $importer = app(ServerSheetImporter::class);
        $importer->apply($this->analyze([
            ['Serveur', 'Type', 'Hyperviseur'],
            ['vm-01', 'Virtuel', 'hyp-01'],
            ['hyp-01', 'Physique', null],
            ['vm-02', 'Virtuel', 'hyp-inconnu'],
        ]));

        $this->assertSame('hyp-01', Server::firstWhere('hostname', 'vm-01')->host->hostname);
        $this->assertSame(ServerKind::Physical, Server::firstWhere('hostname', 'hyp-inconnu')->kind, 'Hôte cité mais absent : créé');
    }

    public function test_missing_server_column_is_an_explicit_error(): void
    {
        $this->expectExceptionMessage('Colonne du nom de serveur introuvable');
        $this->analyze([['Adresse', 'OS'], ['10.0.0.1', 'WS']]);
    }

    public function test_soft_deleted_servers_are_restored(): void
    {
        Server::factory()->create(['hostname' => 'hyp01'])->delete();

        $plan = $this->analyze(self::SHEET);
        $this->assertSame('restore', $plan['rows'][1]['action']);
        app(ServerSheetImporter::class)->apply($plan);

        $this->assertNotSoftDeleted(Server::firstWhere('hostname', 'hyp01'));
    }

    // ------------------------------------------------------------- Écran

    public function test_upload_preview_then_confirm(): void
    {
        $editor = User::factory()->editor()->create();
        $file = new UploadedFile(XlsxBuilder::make(self::SHEET), 'Cartho.xlsx', null, null, true);

        $this->actingAs($editor)
            ->post(route('servers.import.preview'), ['file' => $file, 'environment' => 'PROD'])
            ->assertRedirect(route('servers.import'));
        $this->assertSame(0, Server::count());

        $token = null;
        $this->actingAs($editor)->get(route('servers.import'))
            ->assertInertia(function (Assert $page) use (&$token) {
                $page->component('servers/Import')
                    ->where('pending.file', 'Cartho.xlsx')
                    ->where('pending.plan.summary.create', 4)
                    ->has('pending.plan.rows', 4);
                $token = $page->toArray()['props']['pending']['token'];
            });

        $this->actingAs($editor)->post(route('servers.import.store'), ['token' => $token])->assertRedirect(route('servers.index'));
        $this->assertSame(4, Server::count());

        // L'aperçu est consommé : une seconde confirmation ne fait rien
        $this->actingAs($editor)->post(route('servers.import.store'), ['token' => $token])->assertRedirect(route('servers.import'));
        $this->assertSame(4, Server::count());
    }

    public function test_a_wrong_token_does_not_import(): void
    {
        $editor = User::factory()->editor()->create();
        $file = new UploadedFile(XlsxBuilder::make(self::SHEET), 'f.xlsx', null, null, true);
        $this->actingAs($editor)->post(route('servers.import.preview'), ['file' => $file, 'environment' => 'PROD']);

        $this->actingAs($editor)->post(route('servers.import.store'), ['token' => 'autre'])->assertRedirect(route('servers.import'));
        $this->assertSame(0, Server::count());
    }

    public function test_invalid_files_are_rejected_with_a_clear_message(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->post(route('servers.import.preview'), ['file' => UploadedFile::fake()->create('photo.png', 10, 'image/png'), 'environment' => 'PROD'])
            ->assertSessionHasErrors('file');

        $this->actingAs($editor)
            ->post(route('servers.import.preview'), ['file' => new UploadedFile(XlsxBuilder::make([['Adresse'], ['10.0.0.1']]), 'f.xlsx', null, null, true), 'environment' => 'PROD'])
            ->assertSessionHasErrors(['file' => 'Colonne du nom de serveur introuvable : la première ligne doit contenir un en-tête « Serveur » (ou « Nom », « Hostname »).']);
    }

    public function test_readers_cannot_import(): void
    {
        $reader = User::factory()->create();

        $this->actingAs($reader)->get(route('servers.import'))->assertForbidden();
        $this->actingAs($reader)->post(route('servers.import.preview'), ['environment' => 'PROD'])->assertForbidden();
    }

    public function test_template_can_be_downloaded(): void
    {
        $csv = $this->actingAs(User::factory()->editor()->create())->get(route('servers.import.template'))->streamedContent();

        $this->assertStringContainsString('Serveur;IP;OS;Type;Localisation;Criticité', $csv);
        // Le modèle est lui-même importable
        $path = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($path, $csv);
        $this->assertSame(2, app(ServerSheetImporter::class)->analyze(SpreadsheetReader::read($path, 'modele.csv'))['summary']['create']);
    }

    // ------------------------------------------------------------- Commande

    public function test_command_dry_run_then_import(): void
    {
        $path = XlsxBuilder::make(self::SHEET);

        $this->artisan('merimap:import-servers', ['file' => $path, '--dry-run' => true])
            ->expectsOutputToContain('Simulation')
            ->assertSuccessful();
        $this->assertSame(0, Server::count());

        $this->artisan('merimap:import-servers', ['file' => $path])->expectsOutputToContain('4 créé(s)')->assertSuccessful();
        $this->assertSame(4, Server::count());
    }
}
