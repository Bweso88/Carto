<?php

namespace App\Enums;

enum ServerStatus: string
{
    use HasLabel;

    case Planned = 'planned';
    case Active = 'active';
    case Maintenance = 'maintenance';
    case Decommissioned = 'decommissioned';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Prévu',
            self::Active => 'En service',
            self::Maintenance => 'En maintenance',
            self::Decommissioned => 'Décommissionné',
        };
    }
}
