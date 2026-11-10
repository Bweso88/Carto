<?php

namespace App\Models;

use App\Enums\DeploymentRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Déploiement d'une application sur un serveur (table pivot enrichie).
 *
 * @property DeploymentRole $role
 */
#[Fillable(['application_id', 'server_id', 'environment_id', 'role', 'notes'])]
class Deployment extends Pivot
{
    protected $table = 'deployments';

    public $incrementing = true;

    protected $attributes = ['role' => 'application'];

    /**
     * Un déploiement dont le serveur ou l'application est à la corbeille
     * n'existe plus pour l'interface, la topologie ni l'analyse d'impact.
     * Il réapparaît si l'élément est restauré.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('active', fn (Builder $query) => $query->whereHas('server')->whereHas('application'));
    }

    protected function casts(): array
    {
        return ['role' => DeploymentRole::class];
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** @return BelongsTo<Environment, $this> */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }
}
