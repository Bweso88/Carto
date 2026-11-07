<?php

namespace Tests\Feature\Cmdb;

use App\Enums\FlowProtocol;
use App\Models\Application;
use App\Models\ApplicationFlow;
use App\Models\IpAddress;
use App\Models\Server;
use App\Models\Technology;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CmdbModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_applications_get_sequential_cmdb_codes(): void
    {
        $first = Application::factory()->create();
        $second = Application::factory()->create();
        $first->delete(); // suppression logique : le code n'est jamais réattribué

        $third = Application::factory()->create();

        $this->assertSame(['APP-0001', 'APP-0002', 'APP-0003'], [$first->code, $second->code, $third->code]);
    }

    public function test_servers_can_be_searched_by_ip(): void
    {
        $server = Server::factory()->create(['hostname' => 'srv-erp-01']);
        IpAddress::factory()->for($server)->create(['address' => '10.14.88.24']);
        Server::factory()->create(['hostname' => 'srv-autre']);

        $this->assertSame(['srv-erp-01'], Server::search('10.14.88')->pluck('hostname')->all());
        $this->assertSame(['srv-erp-01'], Server::search('erp')->pluck('hostname')->all());
    }

    public function test_ip_version_is_detected(): void
    {
        $this->assertSame(6, IpAddress::factory()->create(['address' => 'fe80::1'])->version);
        $this->assertSame(4, IpAddress::factory()->create(['address' => '10.0.0.1'])->version);
    }

    public function test_technology_obsolescence(): void
    {
        $obsolete = Technology::factory()->obsolete()->create();
        $current = Technology::factory()->create(['end_of_support_at' => now()->addYears(2)]);

        $this->assertTrue($obsolete->isObsolete());
        $this->assertFalse($current->isObsolete());
        $this->assertTrue($current->isObsolete(now()->addYears(3)));
        $this->assertEquals([$obsolete->id], Technology::unsupportedBefore(now())->pluck('id')->all());
    }

    public function test_flow_protocol_is_guessed_from_free_labels(): void
    {
        $this->assertSame(FlowProtocol::Rest, FlowProtocol::guess('API REST'));
        $this->assertSame(FlowProtocol::Ldap, FlowProtocol::guess('Active Directory'));
        $this->assertSame(FlowProtocol::Sso, FlowProtocol::guess('OAuth'));
        $this->assertSame(FlowProtocol::DatabaseLink, FlowProtocol::guess('Database Link'));
        $this->assertSame(FlowProtocol::MessageQueue, FlowProtocol::guess('Message Queue'));
        $this->assertSame(FlowProtocol::FileTransfer, FlowProtocol::guess('File Transfer'));
        $this->assertSame(FlowProtocol::Other, FlowProtocol::guess(null));
    }

    public function test_role_gates(): void
    {
        $reader = User::factory()->create();
        $editor = User::factory()->editor()->create();
        $admin = User::factory()->admin()->create();

        $this->assertFalse(Gate::forUser($reader)->allows('edit-cmdb'));
        $this->assertTrue(Gate::forUser($editor)->allows('edit-cmdb'));
        $this->assertFalse(Gate::forUser($editor)->allows('manage-users'));
        $this->assertTrue(Gate::forUser($admin)->allows('manage-users'));
    }

    public function test_new_accounts_are_readers_by_default(): void
    {
        $this->assertSame('reader', User::factory()->create()->role->value);
    }

    public function test_demo_seeder_builds_a_coherent_dataset(): void
    {
        $this->seed(DatabaseSeeder::class);

        $vm = Server::where('hostname', 'srv-app-prod-04')->firstOrFail();
        $this->assertSame('esx-par-01', $vm->host->hostname);
        $this->assertGreaterThan(0, $vm->applications()->count());
        $this->assertSame(1, ApplicationFlow::where('is_encrypted', false)->count());
    }
}
