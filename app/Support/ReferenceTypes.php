<?php

namespace App\Support;

use App\Enums\TechnologyCategory;
use App\Models\BusinessDomain;
use App\Models\Environment;
use App\Models\Site;
use App\Models\Team;
use App\Models\Technology;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Description des référentiels administrables depuis l'écran « Référentiels ».
 * Même idée que crud.php dans Kerberos, mais typée et validée.
 */
final class ReferenceTypes
{
    /**
     * @return array<string, array{
     *   model: class-string<Model>, title: string, singular: string,
     *   fields: list<array{name: string, label: string, type: string, options?: list<array{value: string, label: string}>, required?: bool}>,
     *   columns: list<string>, usage: list<string>
     * }>
     */
    public static function all(): array
    {
        return [
            'sites' => [
                'model' => Site::class, 'title' => 'Sites', 'singular' => 'site',
                'fields' => [
                    ['name' => 'name', 'label' => 'Nom', 'type' => 'text', 'required' => true],
                    ['name' => 'code', 'label' => 'Code', 'type' => 'text'],
                    ['name' => 'kind', 'label' => 'Type', 'type' => 'select', 'required' => true, 'options' => [
                        ['value' => 'datacenter', 'label' => 'Datacenter'],
                        ['value' => 'cloud_region', 'label' => 'Région cloud'],
                        ['value' => 'office', 'label' => 'Bureau / agence'],
                    ]],
                    ['name' => 'address', 'label' => 'Adresse', 'type' => 'text'],
                ],
                'columns' => ['name', 'code', 'kind'],
                'usage' => ['servers'],
            ],
            'environments' => [
                'model' => Environment::class, 'title' => 'Environnements', 'singular' => 'environnement',
                'fields' => [
                    ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => true],
                    ['name' => 'name', 'label' => 'Nom', 'type' => 'text', 'required' => true],
                    ['name' => 'sort_order', 'label' => 'Ordre', 'type' => 'number'],
                    ['name' => 'is_production', 'label' => 'Production', 'type' => 'boolean'],
                ],
                'columns' => ['code', 'name', 'is_production'],
                'usage' => ['servers', 'deployments'],
            ],
            'teams' => [
                'model' => Team::class, 'title' => 'Équipes', 'singular' => 'équipe',
                'fields' => [
                    ['name' => 'name', 'label' => 'Nom', 'type' => 'text', 'required' => true],
                    ['name' => 'lead_name', 'label' => 'Responsable', 'type' => 'text'],
                    ['name' => 'email', 'label' => 'E-mail', 'type' => 'email'],
                    ['name' => 'on_call_phone', 'label' => 'Astreinte', 'type' => 'text'],
                ],
                'columns' => ['name', 'lead_name', 'email', 'on_call_phone'],
                'usage' => ['technicallyOwnedApplications', 'businessOwnedApplications', 'servers'],
            ],
            'vendors' => [
                'model' => Vendor::class, 'title' => 'Éditeurs & constructeurs', 'singular' => 'éditeur',
                'fields' => [
                    ['name' => 'name', 'label' => 'Nom', 'type' => 'text', 'required' => true],
                    ['name' => 'website', 'label' => 'Site web', 'type' => 'url'],
                    ['name' => 'support_contact', 'label' => 'Contact support', 'type' => 'text'],
                ],
                'columns' => ['name', 'website', 'support_contact'],
                'usage' => ['applications', 'technologies'],
            ],
            'business-domains' => [
                'model' => BusinessDomain::class, 'title' => 'Domaines métier', 'singular' => 'domaine',
                'fields' => [
                    ['name' => 'name', 'label' => 'Nom', 'type' => 'text', 'required' => true],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'text'],
                ],
                'columns' => ['name', 'description'],
                'usage' => ['applications'],
            ],
            'technologies' => [
                'model' => Technology::class, 'title' => 'Technologies & cycle de vie', 'singular' => 'technologie',
                'fields' => [
                    ['name' => 'category', 'label' => 'Catégorie', 'type' => 'select', 'required' => true, 'options' => TechnologyCategory::options()],
                    ['name' => 'name', 'label' => 'Nom', 'type' => 'text', 'required' => true],
                    ['name' => 'version', 'label' => 'Version', 'type' => 'text'],
                    ['name' => 'vendor_id', 'label' => 'Éditeur', 'type' => 'select', 'options' => CmdbOptions::vendors()],
                    ['name' => 'end_of_support_at', 'label' => 'Fin de support', 'type' => 'date'],
                    ['name' => 'end_of_life_at', 'label' => 'Fin de vie', 'type' => 'date'],
                ],
                'columns' => ['name', 'version', 'category', 'end_of_support_at', 'end_of_life_at'],
                'usage' => ['applications', 'servers'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function get(string $type): array
    {
        return self::all()[$type] ?? abort(404);
    }

    /** @return array<string, mixed> */
    public static function rules(string $type, ?Model $record = null): array
    {
        $table = (new (self::get($type)['model']))->getTable();
        $unique = fn (string $col) => Rule::unique($table, $col)->ignore($record?->getKey());

        return match ($type) {
            'sites' => ['name' => ['required', 'string', 'max:150', $unique('name')], 'code' => ['nullable', 'string', 'max:30', $unique('code')],
                'kind' => ['required', Rule::in(['datacenter', 'cloud_region', 'office'])], 'address' => ['nullable', 'string', 'max:255']],
            'environments' => ['code' => ['required', 'string', 'max:20', 'alpha_dash', $unique('code')], 'name' => ['required', 'string', 'max:100'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'], 'is_production' => ['boolean']],
            'teams' => ['name' => ['required', 'string', 'max:150', $unique('name')], 'lead_name' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'], 'on_call_phone' => ['nullable', 'string', 'max:50']],
            'vendors' => ['name' => ['required', 'string', 'max:150', $unique('name')], 'website' => ['nullable', 'url', 'max:255'],
                'support_contact' => ['nullable', 'string', 'max:255']],
            'business-domains' => ['name' => ['required', 'string', 'max:150', $unique('name')], 'description' => ['nullable', 'string', 'max:1000']],
            'technologies' => ['category' => ['required', Rule::enum(TechnologyCategory::class)], 'name' => ['required', 'string', 'max:150'],
                'version' => ['nullable', 'string', 'max:50',
                    Rule::unique('technologies', 'version')->ignore($record?->getKey())
                        ->where(fn ($q) => $q->where('category', request('category'))->where('name', request('name')))],
                'vendor_id' => ['nullable', 'exists:vendors,id'],
                'end_of_support_at' => ['nullable', 'date'], 'end_of_life_at' => ['nullable', 'date', 'after_or_equal:end_of_support_at']],
        };
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function withUsage(string $type, Builder $query): Builder
    {
        return $query->withCount(self::get($type)['usage']);
    }
}
