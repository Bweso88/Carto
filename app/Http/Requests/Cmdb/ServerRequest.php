<?php

namespace App\Http\Requests\Cmdb;

use App\Enums\Criticality;
use App\Enums\ServerKind;
use App\Enums\ServerStatus;
use App\Enums\TechnologyCategory;
use App\Models\Server;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit-cmdb');
    }

    protected function prepareForValidation(): void
    {
        // Les adresses IP arrivent en texte libre : une par ligne (ou séparées par , ;)
        $ips = preg_split('/[\s,;]+/', (string) $this->input('ip_addresses_text'), -1, PREG_SPLIT_NO_EMPTY);
        $this->merge(['ip_addresses' => array_values(array_unique($ips))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Server|null $server */
        $server = $this->route('server');

        return [
            'hostname' => ['required', 'string', 'max:150', 'regex:/^[A-Za-z0-9][A-Za-z0-9.\-_]*$/',
                Rule::unique('servers', 'hostname')->ignore($server?->id)],
            'fqdn' => ['nullable', 'string', 'max:255'],
            'kind' => ['required', Rule::enum(ServerKind::class)],
            'status' => ['required', Rule::enum(ServerStatus::class)],
            'criticality' => ['nullable', Rule::enum(Criticality::class)],
            'environment_id' => ['nullable', 'exists:environments,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'os_id' => ['nullable', Rule::exists('technologies', 'id')->where('category', TechnologyCategory::OperatingSystem->value)],
            'owner_team_id' => ['nullable', 'exists:teams,id'],
            'host_id' => ['nullable', 'exists:servers,id', Rule::notIn(array_filter([$server?->id]))],
            'manufacturer_id' => ['nullable', 'exists:vendors,id'],
            'model' => ['nullable', 'string', 'max:150'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'cpu_cores' => ['nullable', 'integer', 'min:1', 'max:4096'],
            'ram_gb' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'storage_gb' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'commissioned_at' => ['nullable', 'date'],
            'warranty_end_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'ip_addresses' => ['array', 'max:32'],
            'ip_addresses.*' => ['ip'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            // Empêche les boucles d'hébergement (A héberge B qui héberge A)
            $server = $this->route('server');
            $hostId = $this->input('host_id');
            $seen = [];
            while ($server && $hostId && ! isset($seen[$hostId])) {
                if ((int) $hostId === $server->id) {
                    $validator->errors()->add('host_id', 'Ce choix créerait une boucle d\'hébergement.');

                    return;
                }
                $seen[$hostId] = true;
                $hostId = Server::whereKey($hostId)->value('host_id');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'hostname' => 'nom d\'hôte', 'kind' => 'type', 'status' => 'statut', 'criticality' => 'criticité',
            'environment_id' => 'environnement', 'site_id' => 'site', 'os_id' => 'système', 'host_id' => 'hôte',
            'owner_team_id' => 'équipe', 'manufacturer_id' => 'constructeur', 'ip_addresses.*' => 'adresse IP',
            'cpu_cores' => 'cœurs CPU', 'ram_gb' => 'mémoire', 'storage_gb' => 'stockage',
        ];
    }

    public function messages(): array
    {
        return ['hostname.regex' => 'Le nom d\'hôte ne doit contenir que lettres, chiffres, points, tirets et soulignés.'];
    }
}
