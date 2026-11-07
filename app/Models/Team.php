<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'lead_name', 'on_call_phone'])]
class Team extends Model
{
    use HasFactory;

    /** @return HasMany<Application, $this> */
    public function technicallyOwnedApplications(): HasMany
    {
        return $this->hasMany(Application::class, 'technical_owner_team_id');
    }

    /** @return HasMany<Application, $this> */
    public function businessOwnedApplications(): HasMany
    {
        return $this->hasMany(Application::class, 'business_owner_team_id');
    }

    /** @return HasMany<Server, $this> */
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class, 'owner_team_id');
    }
}
