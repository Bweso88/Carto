<?php

namespace App\Enums;

enum FlowProtocol: string
{
    use HasLabel;

    case Rest = 'rest';
    case Soap = 'soap';
    case Grpc = 'grpc';
    case FileTransfer = 'file_transfer';
    case DatabaseLink = 'database_link';
    case MessageQueue = 'message_queue';
    case Ldap = 'ldap';
    case Sso = 'sso';
    case Smtp = 'smtp';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Rest => 'API REST',
            self::Soap => 'SOAP',
            self::Grpc => 'gRPC',
            self::FileTransfer => 'Transfert de fichiers',
            self::DatabaseLink => 'Lien base de données',
            self::MessageQueue => 'File de messages',
            self::Ldap => 'LDAP / Active Directory',
            self::Sso => 'SSO (SAML / OIDC)',
            self::Smtp => 'SMTP',
            self::Other => 'Autre',
        };
    }

    /**
     * Devine le protocole à partir d'un libellé libre (données Kerberos).
     */
    public static function guess(?string $label): self
    {
        $l = mb_strtolower(trim((string) $label));

        return match (true) {
            $l === '' => self::Other,
            str_contains($l, 'rest') || str_contains($l, 'http') => self::Rest,
            str_contains($l, 'soap') => self::Soap,
            str_contains($l, 'grpc') => self::Grpc,
            str_contains($l, 'file') || str_contains($l, 'ftp') || str_contains($l, 'fichier') => self::FileTransfer,
            str_contains($l, 'database') || str_contains($l, 'db') || str_contains($l, 'odbc') => self::DatabaseLink,
            str_contains($l, 'queue') || str_contains($l, 'mq') || str_contains($l, 'kafka') => self::MessageQueue,
            str_contains($l, 'ldap') || str_contains($l, 'active directory') || $l === 'ad' => self::Ldap,
            str_contains($l, 'sso') || str_contains($l, 'oauth') || str_contains($l, 'saml') || str_contains($l, 'oidc') => self::Sso,
            str_contains($l, 'smtp') || str_contains($l, 'mail') => self::Smtp,
            default => self::Other,
        };
    }
}
