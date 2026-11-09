<?php

namespace App\Support;

use App\Enums\Criticality;
use App\Enums\ServerKind;
use Illuminate\Support\Str;

/**
 * Interprétation des libellés libres trouvés dans les fichiers (Excel, Kerberos…).
 */
final class Normalize
{
    /** Clé de comparaison : minuscules, sans accents ni ponctuation superflue. */
    public static function key(?string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii((string) $value))));
    }

    /**
     * Système d'exploitation : « WS 2012 R2 » -> [Windows Server, 2012 R2], « Linux Rhel9 » -> [Red Hat Enterprise Linux, 9].
     *
     * @return array{0: string, 1: string}|null [nom, version]
     */
    public static function operatingSystem(?string $label): ?array
    {
        $label = trim((string) $label, " \t\n\r\0\x0B'\"");
        if ($label === '') {
            return null;
        }

        $patterns = [
            '/^(?:ws|win(?:dows)?\s*(?:server|srv)|w2k)\s*(.*)$/i' => 'Windows Server',
            '/^(?:linux\s*)?(?:rhel|red\s*hat(?:\s*enterprise\s*linux)?)\s*v?(\d+(?:\.\d+)?)?\s*$/i' => 'Red Hat Enterprise Linux',
            '/^(?:linux\s*)?ubuntu\s*(?:server\s*)?(.*)$/i' => 'Ubuntu',
            '/^(?:linux\s*)?debian\s*(.*)$/i' => 'Debian',
            '/^(?:linux\s*)?(?:centos)\s*(.*)$/i' => 'CentOS',
            '/^(?:linux\s*)?(?:rocky)(?:\s*linux)?\s*(.*)$/i' => 'Rocky Linux',
        ];
        foreach ($patterns as $regex => $name) {
            if (preg_match($regex, $label, $m)) {
                return [$name, strtoupper(trim($m[1] ?? '')) === '' ? '' : trim(preg_replace('/\s+/', ' ', $m[1]))];
            }
        }

        return [$label, ''];
    }

    public static function criticality(?string $label): ?Criticality
    {
        $k = self::key($label);

        return match (true) {
            $k === '' => null,
            in_array($k, ['critique', 'critical', 'vital', 'vitale', 'tier 1', '1', 't1', 'tres elevee'], true) => Criticality::Critical,
            in_array($k, ['a surveiller', 'elevee', 'eleve', 'haute', 'high', 'majeur', 'majeure', 'tier 2', '2', 't2', 'important'], true) => Criticality::High,
            in_array($k, ['moyenne', 'moyen', 'medium', 'modere', 'moderee', 'tier 3', '3', 't3'], true) => Criticality::Medium,
            in_array($k, ['faible', 'basse', 'low', 'mineur', 'tier 4', '4', 't4'], true) => Criticality::Low,
            default => null, // « Non défini », « N/A »… : non évaluée
        };
    }

    public static function serverKind(?string $label): ?ServerKind
    {
        $k = self::key($label);

        return match (true) {
            $k === '' => null,
            str_contains($k, 'phys') || $k === 'baremetal' || $k === 'bare metal' => ServerKind::Physical,
            str_contains($k, 'virt') || $k === 'vm' => ServerKind::Virtual,
            str_contains($k, 'cloud') || in_array($k, ['aws', 'azure', 'gcp', 'saas'], true) => ServerKind::Cloud,
            str_contains($k, 'conten') || str_contains($k, 'container') || str_contains($k, 'pod') || str_contains($k, 'docker') => ServerKind::Container,
            default => null,
        };
    }
}
