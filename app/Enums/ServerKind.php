<?php

namespace App\Enums;

enum ServerKind: string
{
    use HasLabel;

    case Physical = 'physical';
    case Virtual = 'virtual';
    case Cloud = 'cloud';
    case Container = 'container';

    public function label(): string
    {
        return match ($this) {
            self::Physical => 'Physique',
            self::Virtual => 'Virtuel',
            self::Cloud => 'Cloud',
            self::Container => 'Conteneur / Pod',
        };
    }
}
