<?php

namespace App\Enums;

enum LifecycleStatus: string
{
    use HasLabel;

    case Project = 'project';
    case Production = 'production';
    case Retiring = 'retiring';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Project => 'En projet',
            self::Production => 'En production',
            self::Retiring => 'En cours de retrait',
            self::Retired => 'Retirée',
        };
    }
}
