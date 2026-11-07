<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Éditeur logiciel ou constructeur matériel.
 */
#[Fillable(['name', 'website', 'support_contact'])]
class Vendor extends Model
{
    use HasFactory;

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /** @return HasMany<Technology, $this> */
    public function technologies(): HasMany
    {
        return $this->hasMany(Technology::class);
    }
}
