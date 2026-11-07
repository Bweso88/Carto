<?php

namespace App\Http\Requests\Cmdb;

use App\Enums\DeploymentRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit-cmdb');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'server_id' => ['required', 'exists:servers,id',
                Rule::unique('deployments')->where('application_id', $this->route('application')->id)->where('role', $this->input('role'))],
            'role' => ['required', Rule::enum(DeploymentRole::class)],
            'environment_id' => ['nullable', 'exists:environments,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['server_id' => 'serveur', 'role' => 'rôle', 'environment_id' => 'environnement'];
    }

    public function messages(): array
    {
        return ['server_id.unique' => 'Ce serveur a déjà ce rôle pour cette application.'];
    }
}
