<?php

namespace App\Http\Requests\Cmdb;

use App\Enums\FlowProtocol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ajout d'un flux depuis la fiche d'une application.
 * direction = outgoing : l'application consomme la cible ; incoming : l'autre application la consomme.
 */
class FlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit-cmdb');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $current = $this->route('application')->id;

        return [
            'direction' => ['required', Rule::in(['outgoing', 'incoming'])],
            'other_application_id' => ['required', 'exists:applications,id', Rule::notIn([$current])],
            'protocol' => ['required', Rule::enum(FlowProtocol::class)],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'is_encrypted' => ['nullable', 'boolean'],
            'is_critical' => ['boolean'],
            'frequency' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['other_application_id' => 'application', 'protocol' => 'protocole'];
    }

    public function messages(): array
    {
        return ['other_application_id.not_in' => 'Une application ne peut pas dépendre d\'elle-même.'];
    }
}
