<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['server_id', 'address', 'version', 'label', 'is_primary'])]
class IpAddress extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // La version (4 ou 6) est déduite de l'adresse
        static::saving(function (self $ip) {
            $ip->version = filter_var($ip->address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 6 : 4;
        });
    }

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'version' => 'integer'];
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
