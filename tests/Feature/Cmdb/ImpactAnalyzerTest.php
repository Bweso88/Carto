<?php

namespace Tests\Feature\Cmdb;

use App\Enums\Criticality;
use App\Enums\DeploymentRole;
use App\Models\Application;
use App\Models\ApplicationFlow;
use App\Models\Environment;
use App\Models\Server;
use App\Services\Impact\ImpactAnalyzer;
use App\Services\Impact\ImpactStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpactAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    private function deploy(Application $app, Server $server, DeploymentRole $role = DeploymentRole::Application, ?Environment $env = null): void
    {
        $app->deployments()->create(['server_id' => $server->id, 'role' => $role, 'environment_id' => $env?->id]);
    }

    public function test_application_is_down_when_all_servers_of_a_role_fail(): void
    {
        $server = Server::factory()->create();
        $app = Application::factory()->create();
        $this->deploy($app, $server);

        $report = (new ImpactAnalyzer)->simulateServerFailure($server);

        $this->assertSame(ImpactStatus::Down, $report->statusOf($app->id));
    }

    public function test_redundancy_only_degrades_the_application(): void
    {
        [$web1, $web2, $db] = Server::factory()->count(3)->create();
        $app = Application::factory()->create();
        $this->deploy($app, $web1, DeploymentRole::Web);
        $this->deploy($app, $web2, DeploymentRole::Web);
        $this->deploy($app, $db, DeploymentRole::Database);

        $this->assertSame(ImpactStatus::Degraded, (new ImpactAnalyzer)->simulateServerFailure($web1)->statusOf($app->id));
        $this->assertSame(ImpactStatus::Down, (new ImpactAnalyzer)->simulateServerFailure($db)->statusOf($app->id), 'La base n\'est pas redondée');
    }

    public function test_hypervisor_failure_takes_down_its_vms(): void
    {
        $host = Server::factory()->physical()->create();
        $vm = Server::factory()->hostedOn($host)->create();
        $nested = Server::factory()->hostedOn($vm)->create();
        $app = Application::factory()->create();
        $this->deploy($app, $nested);

        $report = (new ImpactAnalyzer)->simulateServerFailure($host);

        $this->assertEqualsCanonicalizing([$host->hostname, $vm->hostname, $nested->hostname], $report->failedServers->pluck('hostname')->all());
        $this->assertSame(ImpactStatus::Down, $report->statusOf($app->id));
    }

    public function test_failure_cascades_through_critical_flows_only(): void
    {
        $server = Server::factory()->create();
        $database = Application::factory()->create(['criticality' => Criticality::Low]);
        $orders = Application::factory()->critical()->create();
        $shop = Application::factory()->critical()->create();
        $reporting = Application::factory()->create(['criticality' => Criticality::Low]);
        $this->deploy($database, $server);

        ApplicationFlow::factory()->between($orders, $database)->create();
        ApplicationFlow::factory()->between($shop, $orders)->create();
        ApplicationFlow::factory()->between($reporting, $database)->nonCritical()->create();

        $report = (new ImpactAnalyzer)->simulateServerFailure($server);

        $this->assertSame(ImpactStatus::Down, $report->statusOf($orders->id));
        $this->assertSame(ImpactStatus::Down, $report->statusOf($shop->id));
        $this->assertSame(ImpactStatus::Degraded, $report->statusOf($reporting->id));
        $this->assertSame(2, $report->applications->get($shop->id)->depth);
        $this->assertSame($orders->id, $report->applications->get($shop->id)->via->id);
        $this->assertSame(2, $report->criticalDownCount());
        $this->assertSame(3, $report->toArray()['summary']['cascade_levels']);
    }

    public function test_dependency_cycles_do_not_loop_forever(): void
    {
        $server = Server::factory()->create();
        [$a, $b] = Application::factory()->count(2)->create();
        $this->deploy($a, $server);
        ApplicationFlow::factory()->between($a, $b)->create();
        ApplicationFlow::factory()->between($b, $a)->create();

        $report = (new ImpactAnalyzer)->simulateServerFailure($server);

        $this->assertSame(ImpactStatus::Down, $report->statusOf($b->id));
        $this->assertCount(2, $report->applications);
    }

    public function test_non_production_deployments_are_ignored_by_default(): void
    {
        $server = Server::factory()->create();
        $app = Application::factory()->create();
        $this->deploy($app, $server, env: Environment::factory()->create(['is_production' => false]));

        $this->assertNull((new ImpactAnalyzer)->simulateServerFailure($server)->statusOf($app->id));
        $this->assertSame(ImpactStatus::Down, (new ImpactAnalyzer)->simulateServerFailure($server, productionOnly: false)->statusOf($app->id));
    }
}
