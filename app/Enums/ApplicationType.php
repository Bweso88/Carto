<?php

namespace App\Enums;

enum ApplicationType: string
{
    use HasLabel;

    case Business = 'business';        // application métier
    case Technical = 'technical';      // service technique partagé (AD, SSO, LDAP, MQ…)
    case Saas = 'saas';                // service hébergé chez un tiers
    case Database = 'database';        // base de données mutualisée

    public function label(): string
    {
        return match ($this) {
            self::Business => 'Application métier',
            self::Technical => 'Service technique',
            self::Saas => 'SaaS',
            self::Database => 'Base de données',
        };
    }
}
