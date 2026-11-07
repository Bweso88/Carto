<?php

namespace App\Enums;

/**
 * Rôle d'un serveur dans le déploiement d'une application.
 */
enum DeploymentRole: string
{
    use HasLabel;

    case Application = 'application';
    case Web = 'web';
    case Database = 'database';
    case Batch = 'batch';
    case Storage = 'storage';

    public function label(): string
    {
        return match ($this) {
            self::Application => 'Serveur applicatif',
            self::Web => 'Frontal web',
            self::Database => 'Base de données',
            self::Batch => 'Traitements batch',
            self::Storage => 'Stockage',
        };
    }
}
