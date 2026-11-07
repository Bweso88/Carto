<?php

namespace App\Services\Impact;

use App\Enums\Criticality;
use App\Models\Server;
use App\Models\Team;
use Illuminate\Support\Collection;

final class ImpactReport
{
    /**
     * @param  Collection<int, Server>  $failedServers  serveurs en panne (VMs hébergées incluses)
     * @param  Collection<int, ImpactedApplication>  $applications  indexées par id d'application
     */
    public function __construct(
        public readonly Collection $failedServers,
        public readonly Collection $applications,
    ) {}

    public function statusOf(int $applicationId): ?ImpactStatus
    {
        return $this->applications->get($applicationId)?->status;
    }

    public function countByStatus(ImpactStatus $status): int
    {
        return $this->applications->filter(fn (ImpactedApplication $i) => $i->status === $status)->count();
    }

    /** Nombre d'applications vitales (Tier 1) indisponibles. */
    public function criticalDownCount(): int
    {
        return $this->applications
            ->filter(fn (ImpactedApplication $i) => $i->status === ImpactStatus::Down
                && $i->application->criticality === Criticality::Critical)
            ->count();
    }

    public function maxDepth(): int
    {
        return (int) $this->applications->max('depth');
    }

    /** Utilisateurs déclarés des applications indisponibles. */
    public function impactedUsers(): int
    {
        return (int) $this->applications
            ->filter(fn (ImpactedApplication $i) => $i->status === ImpactStatus::Down)
            ->sum(fn (ImpactedApplication $i) => $i->application->user_count ?? 0);
    }

    /**
     * Score de 0 à 100 : poids de chaque application touchée selon sa criticité
     * (indisponible = poids plein, dégradée = moitié), plafonné à 100.
     */
    public function score(): int
    {
        $weights = ['critical' => 25, 'high' => 12, 'medium' => 5, 'low' => 2];
        $score = $this->applications->sum(function (ImpactedApplication $i) use ($weights) {
            $w = $weights[$i->application->criticality?->value ?? 'low'] ?? 2;

            return $i->status === ImpactStatus::Down ? $w : $w / 2;
        });

        return (int) min(100, round($score));
    }

    /**
     * Équipes techniques à mobiliser, avec les applications qui les concernent.
     *
     * @return list<array<string, mixed>>
     */
    public function teamsToMobilize(): array
    {
        $byTeam = $this->applications
            ->filter(fn (ImpactedApplication $i) => $i->application->technical_owner_team_id !== null)
            ->groupBy(fn (ImpactedApplication $i) => $i->application->technical_owner_team_id);

        $teams = Team::whereIn('id', $byTeam->keys())->get()->keyBy('id');

        return $byTeam->map(fn (Collection $items, $teamId) => [
            ...$teams[$teamId]->only('id', 'name', 'lead_name', 'email', 'on_call_phone'),
            'applications' => $items->map(fn (ImpactedApplication $i) => [
                'code' => $i->application->code, 'name' => $i->application->name, 'status' => $i->status->value,
            ])->values()->all(),
            'down' => $items->filter(fn ($i) => $i->status === ImpactStatus::Down)->count(),
        ])->sortByDesc('down')->values()->all();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'failed_servers' => $this->failedServers->map(fn (Server $s) => $s->only('id', 'hostname'))->values()->all(),
            'applications' => $this->applications->sortBy([['depth', 'asc'], ['status', 'asc']])->map->toArray()->values()->all(),
            'teams' => $this->teamsToMobilize(),
            'summary' => [
                'down' => $this->countByStatus(ImpactStatus::Down),
                'degraded' => $this->countByStatus(ImpactStatus::Degraded),
                'critical_down' => $this->criticalDownCount(),
                'cascade_levels' => $this->applications->isEmpty() ? 0 : $this->maxDepth() + 1,
                'impacted_users' => $this->impactedUsers(),
                'score' => $this->score(),
            ],
        ];
    }
}
