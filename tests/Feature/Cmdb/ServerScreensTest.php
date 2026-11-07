<?php

namespace Tests\Feature\Cmdb;

use App\Enums\ServerKind;
use App\Models\Application;
use App\Models\IpAddress;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServerScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceSeeder::class);
    }

    public function test_guests_cannot_see_servers(): void
    {
        $this->get(route('servers.index'))->assertRedirect(route('login'));
    }

    public function test_readers_can_list_and_filter_servers(): void
    {
        $erp = Server::factory()->physical()->create(['hostname' => 'srv-erp-01']);
        IpAddress::factory()->for($erp)->create(['address' => '10.14.88.24']);
        Server::factory()->create(['hostname' => 'srv-web-01']);

        $this->actingAs(User::factory()->create())
            ->get(route('servers.index', ['search' => '10.14.88', 'kind' => 'physical']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('servers/Index')
                ->where('servers.total', 1)
                ->where('servers.data.0.hostname', 'srv-erp-01')
                ->where('servers.data.0.primary_ip', '10.14.88.24')
                ->where('filters.kind', 'physical')
                ->has('options.serverKinds', 4));
    }

    public function test_servers_can_be_sorted(): void
    {
        Server::factory()->create(['hostname' => 'b-srv']);
        Server::factory()->create(['hostname' => 'a-srv']);

        $this->actingAs(User::factory()->create())
            ->get(route('servers.index', ['sort' => '-hostname']))
            ->assertInertia(fn (Assert $page) => $page->where('servers.data.0.hostname', 'b-srv'));
    }

    public function test_server_page_shows_guests_and_applications(): void
    {
        $host = Server::factory()->physical()->create();
        $vm = Server::factory()->hostedOn($host)->create();
        Application::factory()->create(['name' => 'ERP'])->deployments()->create(['server_id' => $vm->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('servers.show', $vm))
            ->assertInertia(fn (Assert $page) => $page
                ->component('servers/Show')
                ->where('server.host.hostname', $host->hostname)
                ->where('deployments.0.application.name', 'ERP'));
    }

    public function test_readers_cannot_create_servers(): void
    {
        $reader = User::factory()->create();

        $this->actingAs($reader)->get(route('servers.create'))->assertForbidden();
        $this->actingAs($reader)->post(route('servers.store'), ['hostname' => 'x', 'kind' => 'virtual', 'status' => 'active'])->assertForbidden();
        $this->assertSame(0, Server::count());
    }

    public function test_editors_can_create_a_server_with_ip_addresses(): void
    {
        $host = Server::factory()->physical()->create();

        $response = $this->actingAs(User::factory()->editor()->create())->post(route('servers.store'), [
            'hostname' => 'srv-new-01',
            'kind' => 'virtual',
            'status' => 'active',
            'criticality' => 'high',
            'host_id' => $host->id,
            'ip_addresses_text' => "10.0.0.5\n10.0.0.6, fe80::1",
        ]);

        $server = Server::where('hostname', 'srv-new-01')->firstOrFail();
        $response->assertRedirect(route('servers.show', $server));
        $this->assertSame(ServerKind::Virtual, $server->kind);
        $this->assertSame($host->id, $server->host_id);
        $this->assertSame('10.0.0.5', $server->primaryIp->address);
        $this->assertSame(3, $server->ipAddresses()->count());
    }

    public function test_server_validation(): void
    {
        Server::factory()->create(['hostname' => 'srv-taken']);

        $this->actingAs(User::factory()->editor()->create())
            ->post(route('servers.store'), [
                'hostname' => 'srv-taken',
                'kind' => 'spaceship',
                'status' => 'active',
                'ip_addresses_text' => '10.0.0.999',
            ])
            ->assertSessionHasErrors(['hostname', 'kind', 'ip_addresses.0']);
    }

    public function test_a_server_cannot_host_itself_through_a_loop(): void
    {
        $a = Server::factory()->physical()->create();
        $b = Server::factory()->physical()->create(['host_id' => $a->id]);

        $this->actingAs(User::factory()->editor()->create())
            ->put(route('servers.update', $a), ['hostname' => $a->hostname, 'kind' => 'physical', 'status' => 'active', 'host_id' => $b->id])
            ->assertSessionHasErrors('host_id');
    }

    public function test_update_replaces_ip_addresses(): void
    {
        $server = Server::factory()->create();
        IpAddress::factory()->for($server)->create(['address' => '10.0.0.1']);

        $this->actingAs(User::factory()->editor()->create())
            ->put(route('servers.update', $server), [
                'hostname' => 'renamed', 'kind' => 'virtual', 'status' => 'maintenance', 'ip_addresses_text' => '10.0.0.2',
            ])
            ->assertRedirect(route('servers.show', $server));

        $server->refresh();
        $this->assertSame('renamed', $server->hostname);
        $this->assertSame(['10.0.0.2'], $server->ipAddresses()->pluck('address')->all());
    }

    public function test_delete_is_a_soft_delete(): void
    {
        $server = Server::factory()->create();

        $this->actingAs(User::factory()->editor()->create())
            ->delete(route('servers.destroy', $server))
            ->assertRedirect(route('servers.index'));

        $this->assertSoftDeleted($server);
    }

    public function test_csv_export_respects_filters_and_neutralizes_formulas(): void
    {
        Server::factory()->physical()->create(['hostname' => 'srv-phys', 'description' => '=HYPERLINK("x")']);
        Server::factory()->create(['hostname' => 'srv-vm']);

        $response = $this->actingAs(User::factory()->create())->get(route('servers.export', ['kind' => 'physical']));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('srv-phys', $csv);
        $this->assertStringNotContainsString('srv-vm', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }
}
