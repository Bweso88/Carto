<?php

namespace App\Http\Resources;

use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Server */
class ServerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hostname' => $this->hostname,
            'fqdn' => $this->fqdn,
            'kind' => EnumValue::of($this->kind),
            'status' => EnumValue::of($this->status),
            'criticality' => EnumValue::of($this->criticality),
            'primary_ip' => $this->whenLoaded('primaryIp', fn () => $this->primaryIp?->address),
            'ip_addresses' => $this->whenLoaded('ipAddresses', fn () => $this->ipAddresses->map(fn ($ip) => [
                'address' => $ip->address, 'version' => $ip->version, 'label' => $ip->label, 'is_primary' => $ip->is_primary,
            ])),
            'environment' => $this->whenLoaded('environment', fn () => $this->environment?->only('id', 'code', 'name', 'is_production')),
            'site' => $this->whenLoaded('site', fn () => $this->site?->only('id', 'name')),
            'os' => $this->whenLoaded('os', fn () => $this->os ? TechnologyResource::make($this->os)->resolve() : null),
            'owner_team' => $this->whenLoaded('ownerTeam', fn () => $this->ownerTeam?->only('id', 'name', 'lead_name', 'email')),
            'manufacturer' => $this->whenLoaded('manufacturer', fn () => $this->manufacturer?->only('id', 'name')),
            'host' => $this->whenLoaded('host', fn () => $this->host?->only('id', 'hostname')),
            'guests' => $this->whenLoaded('guests', fn () => $this->guests->map->only('id', 'hostname')),
            'applications_count' => $this->whenCounted('deployments'),
            'guests_count' => $this->whenCounted('guests'),
            'model' => $this->model,
            'serial_number' => $this->serial_number,
            'cpu_cores' => $this->cpu_cores,
            'ram_gb' => $this->ram_gb,
            'storage_gb' => $this->storage_gb,
            'commissioned_at' => $this->commissioned_at?->toDateString(),
            'warranty_end_at' => $this->warranty_end_at?->toDateString(),
            'description' => $this->description,
            'kerberos_id' => $this->kerberos_id,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
