<?php

namespace Tests\Feature\Cmdb;

use App\Enums\FlowProtocol;
use App\Models\Application;
use App\Models\ApplicationFlow;
use App\Models\Server;
use App\Models\Technology;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApplicationScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceSeeder::class);
    }

    public function test_list_filters_by_criticality_and_search(): void
    {
        Application::factory()->critical()->create(['name' => 'Paie']);
        Application::factory()->create(['name' => 'Paie archive', 'criticality' => 'low']);
        Application::factory()->critical()->create(['name' => 'CRM']);

        $this->actingAs(User::factory()->create())
            ->get(route('applications.index', ['search' => 'paie', 'criticality' => 'critical']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('applications/Index')
                ->where('applications.total', 1)
                ->where('applications.data.0.name', 'Paie'));
    }

    public function test_editors_create_an_application_with_its_stack(): void
    {
        $php = Technology::where('name', 'PHP')->where('version', '8.3')->firstOrFail();

        $this->actingAs(User::factory()->editor()->create())->post(route('applications.store'), [
            'name' => 'Portail RH',
            'type' => 'business',
            'lifecycle_status' => 'production',
            'criticality' => 'high',
            'handles_personal_data' => true,
            'sla_target' => '99.5',
            'technology_ids' => [$php->id],
        ])->assertRedirect();

        $app = Application::where('name', 'Portail RH')->firstOrFail();
        $this->assertSame('APP-0001', $app->code);
        $this->assertTrue($app->handles_personal_data);
        $this->assertSame([$php->id], $app->technologies()->pluck('technologies.id')->all());
    }

    public function test_show_page_contains_deployments_and_flows(): void
    {
        $this->seed(DatabaseSeeder::class);
        $orders = Application::where('name', 'Service Commandes & Panier')->firstOrFail();

        $this->actingAs(User::factory()->create())
            ->get(route('applications.show', $orders))
            ->assertInertia(fn (Assert $page) => $page
                ->component('applications/Show')
                ->has('application.deployments', 2)
                ->has('application.outgoing_flows', 2)
                ->has('application.incoming_flows', 2)
                ->where('application.technical_owner.name', 'Platform SRE')
                ->where('options.applications', fn ($apps) => collect($apps)->doesntContain('value', (string) $orders->id)));
    }

    public function test_deployments_can_be_added_and_removed(): void
    {
        $app = Application::factory()->create();
        $server = Server::factory()->create();
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->post(route('deployments.store', $app), ['server_id' => $server->id, 'role' => 'database'])->assertRedirect();
        $this->actingAs($editor)->post(route('deployments.store', $app), ['server_id' => $server->id, 'role' => 'database'])
            ->assertSessionHasErrors('server_id');

        $deployment = $app->deployments()->sole();
        $this->actingAs($editor)->delete(route('deployments.destroy', $deployment))->assertRedirect();
        $this->assertSame(0, $app->deployments()->count());
    }

    public function test_flows_can_be_declared_in_both_directions(): void
    {
        [$app, $other] = Application::factory()->count(2)->create();
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->post(route('flows.store', $app), [
            'direction' => 'outgoing', 'other_application_id' => $other->id, 'protocol' => 'ldap', 'port' => 636, 'is_encrypted' => true, 'is_critical' => true,
        ])->assertRedirect();
        $this->actingAs($editor)->post(route('flows.store', $app), [
            'direction' => 'incoming', 'other_application_id' => $other->id, 'protocol' => 'rest', 'is_critical' => false,
        ])->assertRedirect();

        $this->assertTrue(ApplicationFlow::where(['source_application_id' => $app->id, 'target_application_id' => $other->id, 'protocol' => FlowProtocol::Ldap])->exists());
        $this->assertTrue(ApplicationFlow::where(['source_application_id' => $other->id, 'target_application_id' => $app->id, 'is_critical' => false])->exists());
    }

    public function test_flow_validation(): void
    {
        $app = Application::factory()->create();
        $other = Application::factory()->create();
        ApplicationFlow::factory()->between($app, $other)->create(['protocol' => 'rest']);
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->post(route('flows.store', $app), ['direction' => 'outgoing', 'other_application_id' => $app->id, 'protocol' => 'rest'])
            ->assertSessionHasErrors('other_application_id');
        $this->actingAs($editor)->post(route('flows.store', $app), ['direction' => 'outgoing', 'other_application_id' => $other->id, 'protocol' => 'rest'])
            ->assertSessionHasErrors('other_application_id');
    }

    public function test_readers_cannot_change_flows_or_deployments(): void
    {
        $flow = ApplicationFlow::factory()->create();
        $reader = User::factory()->create();

        $this->actingAs($reader)->delete(route('flows.destroy', $flow))->assertForbidden();
        $this->actingAs($reader)->post(route('deployments.store', $flow->source), ['server_id' => Server::factory()->create()->id, 'role' => 'web'])->assertForbidden();
        $this->assertModelExists($flow);
    }

    public function test_dashboard_reports_governance_alerts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('kpis.applications', 8)
                ->where('alerts.unencrypted_flows', 1)
                ->where('alerts.servers_obsolete_os', 2)
                ->where('alerts.applications_obsolete_stack', 1)
                ->has('obsoleteServers', 2));
    }

    public function test_export_lists_applications(): void
    {
        Application::factory()->create(['name' => 'Portail Achats']);

        $csv = $this->actingAs(User::factory()->create())->get(route('applications.export'))->streamedContent();

        $this->assertStringContainsString('Portail Achats', $csv);
        $this->assertStringContainsString('Code;Application;Type', $csv);
    }
}
