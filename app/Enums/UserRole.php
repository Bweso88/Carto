<?php

namespace App\Enums;

/**
 * Rôles applicatifs (remplacent super_admin / admin / user de Kerberos).
 */
enum UserRole: string
{
    use HasLabel;

    case Admin = 'admin';     // Kerberos : super_admin — tout, y compris les utilisateurs
    case Editor = 'editor';   // Kerberos : admin — lecture + écriture de la CMDB
    case Reader = 'reader';   // Kerberos : user — lecture seule

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Editor => 'Contributeur',
            self::Reader => 'Lecteur',
        };
    }

    public function canEditCmdb(): bool
    {
        return $this !== self::Reader;
    }

    public function canManageUsers(): bool
    {
        return $this === self::Admin;
    }
}
