<?php

namespace App\Support;

use App\Enums\ApplicationType;
use App\Enums\Criticality;
use App\Enums\DataClassification;
use App\Enums\DeploymentRole;
use App\Enums\FlowProtocol;
use App\Enums\LifecycleStatus;
use App\Enums\ServerKind;
use App\Enums\ServerStatus;
use App\Enums\TechnologyCategory;
use App\Models\BusinessDomain;
use App\Models\Environment;
use App\Models\Site;
use App\Models\Team;
use App\Models\Technology;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Listes de valeurs envoyées au frontend pour les filtres et formulaires.
 * Format uniforme : [{ value, label }].
 */
final class CmdbOptions
{
    /** @return array<string, list<array{value: string, label: string}>> */
    public static function enums(): array
    {
        return [
            'criticalities' => Criticality::options(),
            'serverKinds' => ServerKind::options(),
            'serverStatuses' => ServerStatus::options(),
            'applicationTypes' => ApplicationType::options(),
            'lifecycleStatuses' => LifecycleStatus::options(),
            'dataClassifications' => DataClassification::options(),
            'deploymentRoles' => DeploymentRole::options(),
            'flowProtocols' => FlowProtocol::options(),
            'technologyCategories' => TechnologyCategory::options(),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    public static function environments(): array
    {
        return Environment::orderBy('sort_order')->get()
            ->map(fn (Environment $e) => ['value' => (string) $e->id, 'label' => $e->name])->all();
    }

    /** @return list<array{value: string, label: string}> */
    public static function sites(): array
    {
        return self::named(Site::query());
    }

    /** @return list<array{value: string, label: string}> */
    public static function vendors(): array
    {
        return self::named(Vendor::query());
    }

    /** @return list<array{value: string, label: string}> */
    public static function teams(): array
    {
        return self::named(Team::query());
    }

    /** @return list<array{value: string, label: string}> */
    public static function businessDomains(): array
    {
        return self::named(BusinessDomain::query());
    }

    /** @return list<array{value: string, label: string}> */
    public static function operatingSystems(): array
    {
        return Technology::where('category', TechnologyCategory::OperatingSystem)
            ->orderBy('name')->orderBy('version')->get()
            ->map(fn (Technology $t) => ['value' => (string) $t->id, 'label' => $t->label()])->all();
    }

    /** @return list<array{value: string, label: string, category: string, obsolete: bool}> */
    public static function stackTechnologies(): array
    {
        return Technology::where('category', '!=', TechnologyCategory::OperatingSystem)
            ->orderBy('category')->orderBy('name')->orderBy('version')->get()
            ->map(fn (Technology $t) => [
                'value' => (string) $t->id,
                'label' => $t->label(),
                'category' => $t->category->value,
                'obsolete' => $t->isObsolete(),
            ])->all();
    }

    /**
     * @param  Builder<Model>  $query
     * @return list<array{value: string, label: string}>
     */
    private static function named($query): array
    {
        return $query->orderBy('name')->get(['id', 'name'])
            ->map(fn ($m) => ['value' => (string) $m->id, 'label' => $m->name])->all();
    }
}
