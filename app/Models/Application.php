<?php

namespace App\Models;

use App\Enums\ApplicationType;
use App\Enums\Criticality;
use App\Enums\DataClassification;
use App\Enums\LifecycleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $code
 * @property string $name
 * @property ApplicationType $type
 * @property LifecycleStatus $lifecycle_status
 * @property Criticality|null $criticality
 * @property DataClassification|null $data_classification
 */
#[Fillable([
    'code', 'name', 'description', 'type', 'lifecycle_status', 'criticality',
    'data_classification', 'handles_personal_data',
    'vendor_id', 'business_owner_team_id', 'technical_owner_team_id',
    'user_base', 'user_count', 'sla_target', 'rto_minutes', 'rpo_minutes',
    'documentation_url', 'kerberos_id',
])]
class Application extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = ['type' => 'business', 'lifecycle_status' => 'production'];

    protected static function booted(): void
    {
        // Code CMDB lisible attribué automatiquement : APP-0001, APP-0002…
        static::creating(function (self $app) {
            if (blank($app->code)) {
                $last = static::withTrashed()->where('code', 'like', 'APP-%')->max('code');
                $next = $last ? ((int) substr($last, 4)) + 1 : 1;
                $app->code = sprintf('APP-%04d', $next);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => ApplicationType::class,
            'lifecycle_status' => LifecycleStatus::class,
            'criticality' => Criticality::class,
            'data_classification' => DataClassification::class,
            'handles_personal_data' => 'boolean',
            'sla_target' => 'decimal:3',
        ];
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }
        $like = '%'.trim($term).'%';
        // whereLike : insensible à la casse sur MySQL, PostgreSQL et SQLite
        $query->where(fn (Builder $q) => $q
            ->whereLike('name', $like)
            ->orWhereLike('code', $like)
            ->orWhereLike('description', $like));
    }

    /**
     * Domaines métier (directions) qui utilisent l'application : un ou plusieurs.
     *
     * @return BelongsToMany<BusinessDomain, $this>
     */
    public function businessDomains(): BelongsToMany
    {
        return $this->belongsToMany(BusinessDomain::class)->orderBy('business_domains.name');
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function businessOwner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'business_owner_team_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function technicalOwner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'technical_owner_team_id');
    }

    /** @return BelongsToMany<Technology, $this> */
    public function technologies(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class);
    }

    /** @return HasMany<Deployment, $this> */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    /** @return BelongsToMany<Server, $this> */
    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'deployments')
            ->using(Deployment::class)
            ->withPivot(['id', 'environment_id', 'role', 'notes'])
            ->withTimestamps();
    }

    /**
     * Flux sortants : ce dont l'application dépend.
     *
     * @return HasMany<ApplicationFlow, $this>
     */
    public function outgoingFlows(): HasMany
    {
        return $this->hasMany(ApplicationFlow::class, 'source_application_id');
    }

    /**
     * Flux entrants : qui dépend de l'application.
     *
     * @return HasMany<ApplicationFlow, $this>
     */
    public function incomingFlows(): HasMany
    {
        return $this->hasMany(ApplicationFlow::class, 'target_application_id');
    }

    /** @return BelongsToMany<Application, $this> */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'application_flows', 'source_application_id', 'target_application_id')
            ->withPivot(['protocol', 'is_critical', 'is_encrypted'])->withTimestamps();
    }

    /** @return BelongsToMany<Application, $this> */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'application_flows', 'target_application_id', 'source_application_id')
            ->withPivot(['protocol', 'is_critical', 'is_encrypted'])->withTimestamps();
    }
}
