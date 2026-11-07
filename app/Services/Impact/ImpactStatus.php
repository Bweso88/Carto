<?php

namespace App\Services\Impact;

enum ImpactStatus: string
{
    case Down = 'down';          // service indisponible
    case Degraded = 'degraded';  // service dégradé (redondance restante ou dépendance non critique)

    public function worst(self $other): self
    {
        return $this === self::Down || $other === self::Down ? self::Down : self::Degraded;
    }
}
