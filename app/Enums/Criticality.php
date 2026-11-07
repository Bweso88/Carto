<?php

namespace App\Enums;

enum Criticality: string
{
    use HasLabel;

    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Tier 1 · Vital',
            self::High => 'Tier 2 · Majeur',
            self::Medium => 'Tier 3 · Modéré',
            self::Low => 'Tier 4 · Faible',
        };
    }

    public function tier(): int
    {
        return match ($this) {
            self::Critical => 1,
            self::High => 2,
            self::Medium => 3,
            self::Low => 4,
        };
    }
}
