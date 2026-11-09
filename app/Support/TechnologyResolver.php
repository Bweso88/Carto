<?php

namespace App\Support;

use App\Enums\TechnologyCategory;
use App\Models\Technology;

/**
 * Retrouve une technologie du catalogue à partir d'un nom et d'une version, ou la crée.
 * « Ubuntu » + « 22.04 » retrouve l'entrée « Ubuntu 22.04 LTS » (et ses dates de fin de vie).
 */
final class TechnologyResolver
{
    public static function find(TechnologyCategory $category, string $name, string $version = ''): ?Technology
    {
        $candidates = Technology::where('category', $category)->get()
            ->filter(fn (Technology $t) => strcasecmp($t->name, $name) === 0);

        return $candidates->first(fn (Technology $t) => strcasecmp($t->version, $version) === 0)
            ?? ($version !== '' ? $candidates->first(fn (Technology $t) => stripos($t->version, $version) === 0) : null);
    }

    public static function resolve(TechnologyCategory $category, string $name, string $version = ''): Technology
    {
        return self::find($category, $name, $version)
            ?? Technology::create(['category' => $category, 'name' => $name, 'version' => $version]);
    }

    /** Système d'exploitation à partir d'un libellé libre (« WS 2012 R2 », « Linux Rhel9 »…). */
    public static function operatingSystem(?string $label, bool $create = true): ?Technology
    {
        $parsed = Normalize::operatingSystem($label);
        if ($parsed === null) {
            return null;
        }

        return $create
            ? self::resolve(TechnologyCategory::OperatingSystem, ...$parsed)
            : self::find(TechnologyCategory::OperatingSystem, ...$parsed);
    }
}
