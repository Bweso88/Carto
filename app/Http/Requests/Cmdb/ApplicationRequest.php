<?php

namespace App\Http\Requests\Cmdb;

use App\Enums\ApplicationType;
use App\Enums\Criticality;
use App\Enums\DataClassification;
use App\Enums\LifecycleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit-cmdb');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::enum(ApplicationType::class)],
            'lifecycle_status' => ['required', Rule::enum(LifecycleStatus::class)],
            'criticality' => ['nullable', Rule::enum(Criticality::class)],
            'data_classification' => ['nullable', Rule::enum(DataClassification::class)],
            'handles_personal_data' => ['boolean'],
            'business_domain_id' => ['nullable', 'exists:business_domains,id'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'business_owner_team_id' => ['nullable', 'exists:teams,id'],
            'technical_owner_team_id' => ['nullable', 'exists:teams,id'],
            'user_base' => ['nullable', 'string', 'max:255'],
            'user_count' => ['nullable', 'integer', 'min:0'],
            'sla_target' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rto_minutes' => ['nullable', 'integer', 'min:0'],
            'rpo_minutes' => ['nullable', 'integer', 'min:0'],
            'documentation_url' => ['nullable', 'url', 'max:255'],
            'technology_ids' => ['array'],
            'technology_ids.*' => ['integer', 'exists:technologies,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nom', 'type' => 'type', 'lifecycle_status' => 'cycle de vie', 'criticality' => 'criticité',
            'data_classification' => 'classification', 'business_domain_id' => 'domaine métier', 'vendor_id' => 'éditeur',
            'business_owner_team_id' => 'responsable métier', 'technical_owner_team_id' => 'responsable technique',
            'user_count' => 'nombre d\'utilisateurs', 'sla_target' => 'SLA', 'rto_minutes' => 'RTO', 'rpo_minutes' => 'RPO',
            'documentation_url' => 'lien de documentation',
        ];
    }
}
