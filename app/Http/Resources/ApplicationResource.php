<?php

namespace App\Http\Resources;

use App\Models\Application;
use App\Models\ApplicationFlow;
use App\Models\Deployment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Application */
class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $flow = fn (ApplicationFlow $f, string $side) => [
            'id' => $f->id,
            'application' => $f->{$side}?->only('id', 'code', 'name'),
            'criticality' => EnumValue::of($f->{$side}?->criticality),
            'protocol' => EnumValue::of($f->protocol),
            'port' => $f->port,
            'is_encrypted' => $f->is_encrypted,
            'is_critical' => $f->is_critical,
            'frequency' => $f->frequency,
            'description' => $f->description,
        ];

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'type' => EnumValue::of($this->type),
            'lifecycle_status' => EnumValue::of($this->lifecycle_status),
            'criticality' => EnumValue::of($this->criticality),
            'data_classification' => EnumValue::of($this->data_classification),
            'handles_personal_data' => $this->handles_personal_data,
            'business_domain' => $this->whenLoaded('businessDomain', fn () => $this->businessDomain?->only('id', 'name')),
            'vendor' => $this->whenLoaded('vendor', fn () => $this->vendor?->only('id', 'name')),
            'business_owner' => $this->whenLoaded('businessOwner', fn () => $this->businessOwner?->only('id', 'name', 'lead_name', 'email')),
            'technical_owner' => $this->whenLoaded('technicalOwner', fn () => $this->technicalOwner?->only('id', 'name', 'lead_name', 'email', 'on_call_phone')),
            'technologies' => $this->whenLoaded('technologies', fn () => TechnologyResource::collection($this->technologies)->resolve()),
            'deployments' => $this->whenLoaded('deployments', fn () => $this->deployments->map(fn (Deployment $d) => [
                'id' => $d->id,
                'role' => EnumValue::of($d->role),
                'environment' => $d->environment?->only('id', 'code', 'name'),
                'notes' => $d->notes,
                'server' => [
                    'id' => $d->server->id,
                    'hostname' => $d->server->hostname,
                    'status' => EnumValue::of($d->server->status),
                    'primary_ip' => $d->server->primaryIp?->address,
                    'os' => $d->server->os ? TechnologyResource::make($d->server->os)->resolve() : null,
                ],
            ])),
            'outgoing_flows' => $this->whenLoaded('outgoingFlows', fn () => $this->outgoingFlows->map(fn ($f) => $flow($f, 'target'))),
            'incoming_flows' => $this->whenLoaded('incomingFlows', fn () => $this->incomingFlows->map(fn ($f) => $flow($f, 'source'))),
            'servers_count' => $this->whenCounted('deployments'),
            'dependencies_count' => $this->whenCounted('outgoingFlows'),
            'dependents_count' => $this->whenCounted('incomingFlows'),
            'user_base' => $this->user_base,
            'user_count' => $this->user_count,
            'sla_target' => $this->sla_target !== null ? (float) $this->sla_target : null,
            'rto_minutes' => $this->rto_minutes,
            'rpo_minutes' => $this->rpo_minutes,
            'documentation_url' => $this->documentation_url,
            'kerberos_id' => $this->kerberos_id,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
