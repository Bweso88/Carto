<?php

namespace App\Http\Resources;

use BackedEnum;

/**
 * Sérialise une énumération sous la forme { value, label } (ou null).
 */
final class EnumValue
{
    /** @return array{value: string, label: string}|null */
    public static function of(?BackedEnum $enum): ?array
    {
        if ($enum === null) {
            return null;
        }

        return ['value' => (string) $enum->value, 'label' => method_exists($enum, 'label') ? $enum->label() : (string) $enum->value];
    }
}
