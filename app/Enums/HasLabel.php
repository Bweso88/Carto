<?php

namespace App\Enums;

/**
 * Comportements communs aux énumérations métier : libellé français
 * et liste d'options prête pour les <select> du frontend.
 */
trait HasLabel
{
    abstract public function label(): string;

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
