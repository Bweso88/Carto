<?php

namespace App\Enums;

enum TechnologyCategory: string
{
    use HasLabel;

    case OperatingSystem = 'os';
    case Language = 'language';
    case Framework = 'framework';
    case Database = 'database';
    case Middleware = 'middleware';

    public function label(): string
    {
        return match ($this) {
            self::OperatingSystem => "Système d'exploitation",
            self::Language => 'Langage',
            self::Framework => 'Framework',
            self::Database => 'Moteur de base de données',
            self::Middleware => 'Middleware',
        };
    }
}
