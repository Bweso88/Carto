<?php

namespace Tests\Feature\Cmdb;

use App\Models\Server;
use App\Models\Site;
use App\Models\Technology;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReferenceScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceSeeder::class);
    }

    public function test_reference_page_lists_records_with_usage(): void
    {
        $site = Site::create(['name' => 'DC Brazzaville', 'kind' => 'datacenter']);
        Server::factory()->count(2)->create(['site_id' => $site->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('references.index', 'sites'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('references/Index')
                ->where('type', 'sites')
                ->where('records.0.values.name', 'DC Brazzaville')
                ->where('records.0.usage', 2)
                ->has('types', 6));
    }

    public function test_technologies_show_obsolescence(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('references.index', 'technologies'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('records', fn ($records) => collect($records)->contains(fn ($r) => $r['values']['name'] === 'Debian' && $r['values']['version'] === '9 Stretch' && $r['obsolete'] === true)));
    }

    public function test_unknown_reference_type_is_a_404(): void
    {
        $this->actingAs(User::factory()->create())->get('/references/planets')->assertNotFound();
    }

    public function test_editors_manage_reference_values(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->post(route('references.store', 'sites'), ['name' => 'Agence Pointe-Noire', 'kind' => 'office'])->assertRedirect();
        $site = Site::where('name', 'Agence Pointe-Noire')->firstOrFail();

        $this->actingAs($editor)->put(route('references.update', ['type' => 'sites', 'id' => $site->id]), ['name' => 'Agence PNR', 'kind' => 'office'])->assertRedirect();
        $this->assertSame('Agence PNR', $site->fresh()->name);

        $this->actingAs($editor)->post(route('references.store', 'sites'), ['name' => 'Agence PNR', 'kind' => 'office'])->assertSessionHasErrors('name');

        $this->actingAs($editor)->delete(route('references.destroy', ['type' => 'sites', 'id' => $site->id]))->assertRedirect();
        $this->assertModelMissing($site);
    }

    public function test_technology_versions_must_be_unique_and_dates_consistent(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->post(route('references.store', 'technologies'), ['category' => 'database', 'name' => 'MySQL', 'version' => '5.7'])
            ->assertSessionHasErrors('version');
        $this->actingAs($editor)->post(route('references.store', 'technologies'), [
            'category' => 'language', 'name' => 'Go', 'version' => '1.23', 'end_of_support_at' => '2026-01-01', 'end_of_life_at' => '2025-01-01',
        ])->assertSessionHasErrors('end_of_life_at');

        $this->actingAs($editor)->post(route('references.store', 'technologies'), ['category' => 'language', 'name' => 'Go'])->assertRedirect();
        $this->assertSame('', Technology::where('name', 'Go')->value('version'));
    }

    public function test_readers_cannot_change_references(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('references.store', 'sites'), ['name' => 'X', 'kind' => 'office'])
            ->assertForbidden();
    }
}
