<?php

namespace App\Models;

use App\Enums\TechnologyCategory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une technologie dans une version donnée (ex. « MySQL 5.7 »), avec ses dates
 * de fin de support : c'est la base du suivi d'obsolescence.
 *
 * @property TechnologyCategory $category
 * @property string $name
 * @property string $version
 * @property CarbonInterface|null $end_of_support_at
 * @property CarbonInterface|null $end_of_life_at
 */
#[Fillable(['category', 'name', 'version', 'vendor_id', 'end_of_support_at', 'end_of_life_at'])]
class Technology extends Model
{
    use HasFactory;

    protected $attributes = ['version' => ''];

    protected function casts(): array
    {
        return [
            'category' => TechnologyCategory::class,
            'end_of_support_at' => 'date',
            'end_of_life_at' => 'date',
        ];
    }

    public function label(): string
    {
        return trim("{$this->name} {$this->version}");
    }

    /** Fin de vie atteinte (ou fin de support si la fin de vie n'est pas connue). */
    public function isObsolete(?CarbonInterface $at = null): bool
    {
        $limit = $this->end_of_life_at ?? $this->end_of_support_at;

        return $limit !== null && $limit->lte($at ?? now());
    }

    /**
     * Technologies obsolètes à une date donnée : même règle que isObsolete()
     * (fin de vie, ou fin de support si la fin de vie n'est pas connue).
     *
     * @param  Builder<self>  $query
     */
    public function scopeUnsupportedBefore(Builder $query, CarbonInterface $date): void
    {
        $query->whereRaw('COALESCE(end_of_life_at, end_of_support_at) <= ?', [$date->toDateString()]);
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsToMany<Application, $this> */
    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class);
    }

    /** @return HasMany<Server, $this> */
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class, 'os_id');
    }
}
