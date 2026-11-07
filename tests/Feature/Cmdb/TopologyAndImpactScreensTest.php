<?php

namespace Tests\Feature\Cmdb;

use App\Models\Application;
use App\Models\ApplicationFlow;
use App\Models\Environment;
use App\Models\Server;
use App\Models\User;
use App\Services\Impact\ImpactAnalyzer;
use App\Services\Impact\ImpactStatus;
use App\Services\Topology\TopologyGraph;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TopologyAndImpactScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_topology_shows_applications_servers_and_all_link_kinds(): void
    {
        $this->actingAs($this->user())
            ->get(route('topology'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('topology/Index')
                ->where('graph.truncated', false)
                ->where('graph.nodes', fn ($nodes) => collect($nodes)->where('type', 'application')->count() === 8
                    && collect($nodes)->where('type', 'server')->isNotEmpty())
                ->where('graph.edges', fn ($edges) => collect($edges)->pluck('kind')->unique()->sort()->values()->all() === ['deployment', 'flow', 'hosting'])
                ->where('filters.environment', (string) Environment::where('code', 'PROD')->value('id')));
    }

    public function test_every_edge_points_to_existing_nodes(): void
    {
        $graph = app(TopologyGraph::class)->build();
        $ids = collect($graph['nodes'])->pluck('id')->flip();

        foreach ($graph['edges'] as $edge) {
            $this->assertTrue($ids->has($edge['source']) && $ids->has($edge['target']), "Lien orphelin {$edge['id']}");
        }
    }

    public function test_topology_can_hide_servers_and_focus_on_an_application(): void
    {
        $ad = Application::where('name', 'Active Directory')->firstOrFail();

        $this->actingAs($this->user())
            ->get(route('topology', ['focus' => $ad->id, 'depth' => 1, 'servers' => '0']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('graph.nodes', fn ($nodes) => collect($nodes)->where('type', 'server')->isEmpty()
                    // AD + ses consommateurs directs (SSO et ERP)
                    && collect($nodes)->pluck('data.label')->sort()->values()->all() === ['Active Directory', 'ERP SAP S/4HANA', 'SSO Identity Gateway']));
    }

    public function test_topology_rejects_invalid_filters(): void
    {
        $this->actingAs($this->user())->get(route('topology', ['depth' => 42]))->assertSessionHasErrors('depth');
    }

    public function test_impact_page_without_selection_shows_the_form(): void
    {
        $this->actingAs($this->user())
            ->get(route('impact'))
            ->assertInertia(fn (Assert $page) => $page->component('impact/Index')->where('report', null)->has('options.servers'));
    }

    public function test_server_failure_simulation_with_graph_and_crisis_teams(): void
    {
        $host = Server::where('hostname', 'esx-par-01')->firstOrFail();

        $this->actingAs($this->user())
            ->get(route('impact', ['servers' => [$host->id]]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('selection.servers.0.label', 'esx-par-01')
                ->where('report.summary.down', 4)
                ->where('report.summary.critical_down', 3)
                ->where('report.summary.impacted_users', 18500)
                ->where('report.summary.score', 87) // 3 vitales (3 x 25) + 1 majeure (12)
                ->has('report.failed_servers', 4)
                ->where('report.teams.0.name', 'Platform SRE')
                // Les déploiements des serveurs en panne sont bien reliés dans le graphe
                ->where('graph.edges', fn ($edges) => collect($edges)->where('kind', 'deployment')->count() === 5)
                ->where('graph.nodes', fn ($nodes) => collect($nodes)->every(fn ($n) => in_array($n['data']['impact'], ['down', 'degraded'], true)))
            );
    }

    public function test_application_failure_simulation(): void
    {
        $ad = Application::where('name', 'Active Directory')->firstOrFail();

        $report = app(ImpactAnalyzer::class)->simulateApplicationFailure($ad);

        $this->assertSame(ImpactStatus::Down, $report->statusOf($ad->id));
        $this->assertTrue($report->applications->get($ad->id)->isRoot);
        $this->assertSame(ImpactStatus::Down, $report->statusOf(Application::where('name', 'SSO Identity Gateway')->value('id')));
        $this->assertSame(ImpactStatus::Down, $report->statusOf(Application::where('name', 'Plateforme E-Commerce Core')->value('id')));
        // Commandes ne dépend de l'ERP que via un flux non critique
        $this->assertSame(ImpactStatus::Degraded, $report->statusOf(Application::where('name', 'Service Commandes & Panier')->value('id')));
        $this->assertCount(0, $report->failedServers);

        $this->actingAs($this->user())
            ->get(route('impact', ['applications' => [$ad->id]]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.applications.0.is_root', true)
                ->where('graph.nodes', fn ($nodes) => collect($nodes)->firstWhere('data.label', 'Active Directory')['data']['root'] === true));
    }

    public function test_impact_score_is_zero_when_nothing_is_touched(): void
    {
        $lonely = Server::factory()->create();

        $this->actingAs($this->user())
            ->get(route('impact', ['servers' => [$lonely->id]]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.summary.score', 0)
                ->where('report.summary.cascade_levels', 0)
                ->has('report.applications', 0));
    }

    public function test_impact_validates_ids(): void
    {
        $this->actingAs($this->user())->get(route('impact', ['servers' => [999999]]))->assertSessionHasErrors('servers.0');
    }

    public function test_soft_deleted_applications_are_not_in_the_graph(): void
    {
        $app = Application::where('name', 'Back-office historique')->firstOrFail();
        $app->delete();

        $graph = app(TopologyGraph::class)->build();

        $this->assertFalse(collect($graph['nodes'])->contains('id', "app-{$app->id}"));
        $this->assertFalse(collect($graph['edges'])->contains(fn ($e) => $e['source'] === "app-{$app->id}"));
        $this->assertSame(1, ApplicationFlow::where('source_application_id', $app->id)->count(), 'Les flux restent en base');
    }
}
