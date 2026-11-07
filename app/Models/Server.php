<?php

namespace App\Models;

use App\Enums\Criticality;
use App\Enums\ServerKind;
use App\Enums\ServerStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $hostname
 * @property ServerKind $kind
 * @property ServerStatus $status
 * @property Criticality|null $criticality
 */
#[Fillable([
    'hostname', 'fqdn', 'kind', 'status', 'criticality',
    'environment_id', 'site_id', 'os_id', 'owner_team_id', 'host_id',
    'manufacturer_id', 'model', 'serial_number', 'cpu_cores', 'ram_gb', 'storage_gb',
    'commissioned_at', 'warranty_end_at', 'description', 'kerberos_id',
])]
class Server extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'kind' => ServerKind::class,
            'status' => ServerStatus::class,
            'criticality' => Criticality::class,
            'commissioned_at' => 'date',
            'warranty_end_at' => 'date',
        ];
    }

    /** Recherche par nom, FQDN ou adresse IP. */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }
        $like = '%'.trim($term).'%';
        // whereLike : insensible à la casse sur MySQL, PostgreSQL et SQLite
        $query->where(fn (Builder $q) => $q
            ->whereLike('hostname', $like)
            ->orWhereLike('fqdn', $like)
            ->orWhereHas('ipAddresses', fn (Builder $ip) => $ip->whereLike('address', $like)));
    }

    /** @return BelongsTo<Environment, $this> */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<Technology, $this> */
    public function os(): BelongsTo
    {
        return $this->belongsTo(Technology::class, 'os_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function ownerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'owner_team_id');
    }

    /** @return BelongsTo<Vendor, $this> */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'manufacturer_id');
    }

    /**
     * Hyperviseur / hôte physique de cette VM.
     *
     * @return BelongsTo<Server, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'host_id');
    }

    /**
     * VMs hébergées sur ce serveur.
     *
     * @return HasMany<Server, $this>
     */
    public function guests(): HasMany
    {
        return $this->hasMany(Server::class, 'host_id');
    }

    /** @return HasMany<IpAddress, $this> */
    public function ipAddresses(): HasMany
    {
        return $this->hasMany(IpAddress::class);
    }

    /** @return HasOne<IpAddress, $this> */
    public function primaryIp(): HasOne
    {
        return $this->hasOne(IpAddress::class)->where('is_primary', true);
    }

    /** @return HasMany<Deployment, $this> */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    /** @return BelongsToMany<Application, $this> */
    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'deployments')
            ->using(Deployment::class)
            ->withPivot(['id', 'environment_id', 'role', 'notes'])
            ->withTimestamps();
    }
}
