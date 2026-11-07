<?php

namespace App\Services\Impact;

use App\Models\Application;

final class ImpactedApplication
{
    /**
     * @param  int  $depth  0 = touchée directement, 1 = dépend d'une app touchée…
     * @param  Application|null  $via  application en amont qui propage la panne (null si cause directe)
     * @param  bool  $isRoot  l'application est elle-même la cible de la simulation
     */
    public function __construct(
        public readonly Application $application,
        public ImpactStatus $status,
        public int $depth,
        public ?Application $via = null,
        public readonly bool $isRoot = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->application->id,
            'code' => $this->application->code,
            'name' => $this->application->name,
            'criticality' => $this->application->criticality?->value,
            'criticality_label' => $this->application->criticality?->label(),
            'status' => $this->status->value,
            'depth' => $this->depth,
            'via' => $this->via?->only('id', 'code', 'name'),
            'is_root' => $this->isRoot,
            'user_count' => $this->application->user_count,
        ];
    }
}
