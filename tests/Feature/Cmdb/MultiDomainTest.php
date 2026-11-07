<?php

namespace Tests\Feature\Cmdb;

use App\Models\Application;
use App\Models\BusinessDomain;
use App\Models\Server;
use App\Models\User;
use App\Services\Topology\TopologyGraph;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Une application peut être utilisée par plusieurs directions (domaines métier).
 */
class MultiDomainTest extends TestCase
{
    use RefreshDatabase;

    private BusinessDomain $finance;

    private BusinessDomain $compta;

    private BusinessDomain $rh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceSeeder::class);
        $this->finance = BusinessDomain::create(['name' => 'Finance']);
        $this->compta = BusinessDomain::create(['name' => 'Comptabilité']);
        $this->rh = BusinessDomain::create(['name' => 'Ressources humaines']);
    }

    public function test_an_application_can_be_created_with_several_domains(): void
    {
        $this->actingAs(User::factory()->editor()->create())->post(route('applications.store'), [
            'name' => 'ERP',
            'type' => 'business',
            'lifecycle_status' => 'production',
            'business_domain_ids' => [$this->finance->id, $this->compta->id],
        ])->assertRedirect();

        $erp = Application::firstWhere('name', 'ERP');
        $this->assertSame(['Comptabilité', 'Finance'], $erp->businessDomains->pluck('name')->all());
    }

    public function test_domains_can_be_changed_and_removed(): void
    {
        $app = Application::factory()->create();
        $app->businessDomains()->sync([$this->finance->id, $this->compta->id]);
        $editor = User::factory()->editor()->create();
        $payload = ['name' => $app->name, 'type' => 'business', 'lifecycle_status' => 'production'];

        $this->actingAs($editor)->put(route('applications.update', $app), [...$payload, 'business_domain_ids' => [$this->rh->id]])->assertRedirect();
        $this->assertSame(['Ressources humaines'], $app->fresh()->businessDomains->pluck('name')->all());

        $this->actingAs($editor)->put(route('applications.update', $app), $payload)->assertRedirect();
        $this->assertCount(0, $app->fresh()->businessDomains);
    }

    public function test_domain_ids_are_validated(): void
    {
        $this->actingAs(User::factory()->editor()->create())->post(route('applications.store'), [
            'name' => 'X', 'type' => 'business', 'lifecycle_status' => 'production',
            'business_domain_ids' => [$this->finance->id, 999999],
        ])->assertSessionHasErrors('business_domain_ids.1');
    }

    public function test_edit_form_receives_current_domains(): void
    {
        $app = Application::factory()->create();
        $app->businessDomains()->sync([$this->finance->id, $this->compta->id]);

        $this->actingAs(User::factory()->editor()->create())
            ->get(route('applications.edit', $app))
            ->assertInertia(fn (Assert $page) => $page
                ->where('values.business_domain_ids', fn ($ids) => collect($ids)->sort()->values()->all() === collect([$this->finance->id, $this->compta->id])->sort()->values()->all()));
    }

    public function test_filtering_by_a_domain_finds_shared_applications(): void
    {
        $erp = Application::factory()->create(['name' => 'ERP']);
        $erp->businessDomains()->sync([$this->finance->id, $this->compta->id]);
        Application::factory()->create(['name' => 'Paie'])->businessDomains()->sync([$this->rh->id]);

        foreach ([$this->finance, $this->compta] as $domain) {
            $this->actingAs(User::factory()->create())
                ->get(route('applications.index', ['domain' => $domain->id]))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('applications.total', 1)
                    ->where('applications.data.0.name', 'ERP')
                    ->has('applications.data.0.business_domains', 2));
        }
    }

    public function test_servers_show_and_filter_by_the_domains_they_serve(): void
    {
        $server = Server::factory()->create(['hostname' => 'srv-erp']);
        $erp = Application::factory()->create();
        $erp->businessDomains()->sync([$this->finance->id, $this->compta->id]);
        $erp->deployments()->create(['server_id' => $server->id]);

        $paie = Application::factory()->create();
        $paie->businessDomains()->sync([$this->rh->id]);
        $paie->deployments()->create(['server_id' => Server::factory()->create(['hostname' => 'srv-paie'])->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('servers.show', $server))
            ->assertInertia(fn (Assert $page) => $page
                ->where('domains', fn ($d) => collect($d)->pluck('name')->all() === ['Comptabilité', 'Finance'])
                ->where('deployments.0.domains', ['Comptabilité', 'Finance']));

        $this->actingAs(User::factory()->create())
            ->get(route('servers.index', ['domain' => $this->compta->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('servers.total', 1)
                ->where('servers.data.0.hostname', 'srv-erp'));
    }

    public function test_topology_domain_filter_includes_shared_applications(): void
    {
        $erp = Application::factory()->create(['name' => 'ERP']);
        $erp->businessDomains()->sync([$this->finance->id, $this->compta->id]);
        Application::factory()->create(['name' => 'Paie'])->businessDomains()->sync([$this->rh->id]);

        $labels = collect(app(TopologyGraph::class)->build(['domain' => $this->compta->id])['nodes'])->pluck('data.label')->all();

        $this->assertSame(['ERP'], $labels);
    }

    public function test_deleting_a_domain_keeps_the_application(): void
    {
        $app = Application::factory()->create();
        $app->businessDomains()->sync([$this->finance->id, $this->compta->id]);

        $this->actingAs(User::factory()->editor()->create())
            ->delete(route('references.destroy', ['type' => 'business-domains', 'id' => $this->finance->id]))
            ->assertRedirect();

        $this->assertModelExists($app);
        $this->assertSame(['Comptabilité'], $app->fresh()->businessDomains->pluck('name')->all());
    }
}
