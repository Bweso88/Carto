<?php

namespace Tests\Feature\Cmdb;

use App\Enums\ApplicationType;
use App\Enums\Criticality;
use App\Enums\FlowProtocol;
use App\Enums\ServerKind;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\ApplicationFlow;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Reproduit la base Kerberos (schema.sql + add_roles.sql + colonne criticité)
 * avec les cas tordus rencontrés dans les vraies données.
 */
class KerberosImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceSeeder::class);

        config(['database.connections.kerberos' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('kerberos');
        $this->createKerberosDatabase();
    }

    private function createKerberosDatabase(): void
    {
        $s = Schema::connection('kerberos');
        foreach (['type' => 'type', 'editeur' => 'editeur', 'dependance' => 'dependance', 'relation' => 'relation',
            'categorie' => 'categorie', 'bdd' => 'moteur', 'langage' => 'langage'] as $table => $column) {
            $s->create($table, function (Blueprint $t) use ($table, $column) {
                $t->increments(match ($table) {
                    'dependance' => 'id_dep', 'categorie' => 'id_cat', default => 'id_'.$table,
                });
                $t->string($column);
            });
        }
        $s->create('serveur', function (Blueprint $t) {
            $t->increments('id_serveur');
            $t->string('serveur');
            $t->string('ip')->nullable();
            $t->string('os')->nullable();
            $t->unsignedInteger('type')->nullable();
            $t->text('comment')->nullable();
            $t->string('local')->nullable();
            $t->unsignedTinyInteger('criticité')->nullable();
        });
        $s->create('application', function (Blueprint $t) {
            $t->increments('id_ap');
            $t->string('app');
            foreach (['serveur', 'editeur', 'dependance', 'interaction', 'activite', 'bdd', 'langage'] as $fk) {
                $t->unsignedInteger($fk)->nullable();
            }
            $t->string('utilisateur')->nullable();
            $t->string('appser')->nullable();
        });
        $s->create('users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('firstname')->nullable();
            $t->string('lastname')->nullable();
            $t->string('username');
            $t->string('role')->nullable();
            $t->string('password')->nullable();
        });

        $db = DB::connection('kerberos');
        $db->table('type')->insert([['type' => 'Physique'], ['type' => 'Virtuel'], ['type' => 'Cloud']]);
        $db->table('editeur')->insert([['editeur' => 'SAP'], ['editeur' => 'Interne']]);
        $db->table('dependance')->insert([['dependance' => 'Active Directory'], ['dependance' => 'Aucune']]);
        $db->table('relation')->insert([['relation' => 'API REST'], ['relation' => 'Database Link']]);
        $db->table('categorie')->insert([['categorie' => 'Finance'], ['categorie' => 'RH']]);
        $db->table('bdd')->insert([['moteur' => 'Oracle'], ['moteur' => 'MySQL 5.7']]);
        $db->table('langage')->insert([['langage' => 'Java'], ['langage' => 'PHP']]);

        $db->table('serveur')->insert([
            ['id_serveur' => 1, 'serveur' => 'srv-veeam', 'ip' => '10.10.1.136', 'os' => "'WS", 'type' => 2, 'comment' => 'Serveur Cluster VMWARE', 'local' => 'Site primaire', 'criticité' => 2],
            ['id_serveur' => 2, 'serveur' => 'sb4web2', 'ip' => '10.10.0.39', 'os' => "'WS", 'type' => 2, 'comment' => 'Hyperviseur: sb4host2', 'local' => 'Site primaire', 'criticité' => 1],
            ['id_serveur' => 3, 'serveur' => 'SRV-DB-01', 'ip' => '192.168.1.30, 192.168.1.31', 'os' => 'Red Hat Enterprise Linux 8', 'type' => 1, 'comment' => null, 'local' => 'Datacenter B', 'criticité' => null],
            ['id_serveur' => 4, 'serveur' => 'srv-veeam', 'ip' => 'à définir', 'os' => '', 'type' => null, 'comment' => null, 'local' => '', 'criticité' => null],
        ]);
        $db->table('application')->insert([
            ['id_ap' => 1, 'app' => 'ERP Principal', 'serveur' => 3, 'editeur' => 1, 'dependance' => 1, 'interaction' => null, 'activite' => 1, 'bdd' => 1, 'langage' => 1, 'utilisateur' => 'Tous les services', 'appser' => 'Application'],
            ['id_ap' => 2, 'app' => 'Intranet RH', 'serveur' => 2, 'editeur' => 2, 'dependance' => 1, 'interaction' => 1, 'activite' => 2, 'bdd' => 2, 'langage' => 2, 'utilisateur' => 'RH', 'appser' => 'Application'],
            ['id_ap' => 3, 'app' => 'Sauvegarde', 'serveur' => 1, 'editeur' => null, 'dependance' => 2, 'interaction' => 2, 'activite' => null, 'bdd' => null, 'langage' => null, 'utilisateur' => null, 'appser' => 'Service'],
            ['id_ap' => 4, 'app' => 'Orpheline', 'serveur' => 99, 'editeur' => null, 'dependance' => null, 'interaction' => null, 'activite' => null, 'bdd' => null, 'langage' => null, 'utilisateur' => null, 'appser' => null],
        ]);
        $db->table('users')->insert([
            ['id' => 1, 'firstname' => 'Super', 'lastname' => 'Admin', 'username' => 'superadmin', 'role' => 'super_admin', 'password' => Hash::make('super123')],
            ['id' => 2, 'firstname' => 'Admin', 'lastname' => 'System', 'username' => 'admin', 'role' => 'admin', 'password' => 'admin123'],
            ['id' => 3, 'firstname' => 'Jean', 'lastname' => 'Dupont', 'username' => 'user', 'role' => 'user', 'password' => Hash::make('user123')],
        ]);
    }

    public function test_it_imports_servers_with_cleaned_data(): void
    {
        $this->artisan('kerberos:import')->assertSuccessful();

        $veeam = Server::where('kerberos_id', 1)->firstOrFail();
        $this->assertSame(ServerKind::Virtual, $veeam->kind);
        $this->assertSame(Criticality::Critical, $veeam->criticality);
        $this->assertSame('WS', $veeam->os->name, "L'apostrophe parasite de Kerberos est retirée");
        $this->assertSame('Site primaire', $veeam->site->name);
        $this->assertSame('PROD', $veeam->environment->code);
        $this->assertSame('10.10.1.136', $veeam->primaryIp->address);

        $db = Server::where('kerberos_id', 3)->firstOrFail();
        $this->assertSame(ServerKind::Physical, $db->kind);
        $this->assertSame(2, $db->ipAddresses()->count(), 'Plusieurs IP dans un même champ sont séparées');
        $this->assertSame('Red Hat Enterprise Linux 8', $db->os->label(), 'Le catalogue existant est réutilisé');

        $this->assertSame('srv-veeam-k4', Server::where('kerberos_id', 4)->value('hostname'), 'Les doublons de nom sont renommés');
        $this->assertSame(0, Server::where('kerberos_id', 4)->first()->ipAddresses()->count(), 'Les IP invalides sont ignorées');
    }

    public function test_hypervisor_mentioned_in_comment_becomes_a_real_host(): void
    {
        $this->artisan('kerberos:import')->assertSuccessful();

        $vm = Server::where('hostname', 'sb4web2')->firstOrFail();
        $this->assertNotNull($vm->host);
        $this->assertSame('sb4host2', $vm->host->hostname);
        $this->assertSame(ServerKind::Physical, $vm->host->kind);
    }

    public function test_it_imports_applications_deployments_and_dependencies(): void
    {
        $this->artisan('kerberos:import')->assertSuccessful();

        $erp = Application::where('kerberos_id', 1)->firstOrFail();
        $this->assertSame('SAP', $erp->vendor->name);
        $this->assertSame(['Finance'], $erp->businessDomains->pluck('name')->all());
        $this->assertSame('Tous les services', $erp->user_base);
        $this->assertSame(['Java', 'Oracle'], $erp->technologies->pluck('name')->sort()->values()->all());
        $this->assertSame(['SRV-DB-01'], $erp->servers->pluck('hostname')->all());

        // « Active Directory » devient un service technique partagé, cible de deux flux
        $ad = Application::where('name', 'Active Directory')->firstOrFail();
        $this->assertSame(ApplicationType::Technical, $ad->type);
        $this->assertSame(2, $ad->incomingFlows()->count());
        $this->assertSame(FlowProtocol::Rest, ApplicationFlow::where('source_application_id', Application::where('kerberos_id', 2)->value('id'))->value('protocol'));
        $this->assertSame(FlowProtocol::Ldap, $erp->outgoingFlows()->first()->protocol);

        // « MySQL 5.7 » correspond à l'entrée du catalogue avec sa date de fin de vie
        $this->assertTrue(Application::where('kerberos_id', 2)->first()->technologies->contains(fn ($t) => $t->label() === 'MySQL 5.7' && $t->isObsolete()));

        // « Aucune » n'est pas une dépendance ; l'interaction sans cible est conservée en description
        $backup = Application::where('kerberos_id', 3)->firstOrFail();
        $this->assertSame(ApplicationType::Technical, $backup->type);
        $this->assertSame(0, $backup->outgoingFlows()->count());
        $this->assertStringContainsString('Database Link', $backup->description);

        $this->assertSame(0, Application::where('kerberos_id', 4)->first()->deployments()->count());
    }

    public function test_it_imports_users_and_keeps_hashed_passwords(): void
    {
        $this->artisan('kerberos:import')
            ->expectsOutputToContain('« admin » : mot de passe non hashé')
            ->assertSuccessful();

        $super = User::where('kerberos_username', 'superadmin')->firstOrFail();
        $this->assertSame(UserRole::Admin, $super->role);
        $this->assertSame('superadmin@kerberos.invalid', $super->email);
        $this->assertTrue(Hash::check('super123', $super->password));
        $this->assertNotNull($super->email_verified_at);

        $admin = User::where('kerberos_username', 'admin')->firstOrFail();
        $this->assertSame(UserRole::Editor, $admin->role);
        $this->assertFalse(Hash::check('admin123', $admin->password), 'Un mot de passe en clair n\'est jamais repris');

        $this->assertSame(UserRole::Reader, User::where('kerberos_username', 'user')->value('role'));
    }

    public function test_import_is_idempotent(): void
    {
        $this->artisan('kerberos:import')->assertSuccessful();
        $snapshot = [Server::count(), Application::count(), ApplicationFlow::count(), User::count(), DB::table('deployments')->count(), DB::table('ip_addresses')->count()];

        $this->artisan('kerberos:import')->assertSuccessful();

        $this->assertSame($snapshot, [Server::count(), Application::count(), ApplicationFlow::count(), User::count(), DB::table('deployments')->count(), DB::table('ip_addresses')->count()]);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('kerberos:import --dry-run')
            ->expectsOutputToContain('aucune donnée')
            ->assertSuccessful();

        $this->assertSame(0, Server::count());
        $this->assertSame(0, Application::count());
        $this->assertSame(0, User::count());
    }

    public function test_existing_server_with_same_hostname_is_completed_not_duplicated(): void
    {
        $existing = Server::factory()->create(['hostname' => 'SRV-DB-01']);

        $this->artisan('kerberos:import')->assertSuccessful();

        $this->assertSame(3, $existing->fresh()->kerberos_id);
        $this->assertSame(1, Server::where('hostname', 'like', 'SRV-DB-01%')->count());
    }
}
