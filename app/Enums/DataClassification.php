<?php

namespace App\Enums;

enum DataClassification: string
{
    use HasLabel;

    case Public = 'public';
    case Internal = 'internal';
    case Confidential = 'confidential';
    case Secret = 'secret';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Internal => 'Interne',
            self::Confidential => 'Confidentiel',
            self::Secret => 'Secret',
        };
    }
}
