<?php

namespace App\Models;

use App\Enums\FlowProtocol;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Flux orienté entre deux applications : la source consomme la cible.
 * Si la cible tombe et que le flux est critique, la source est impactée.
 *
 * @property FlowProtocol $protocol
 * @property bool|null $is_encrypted
 * @property bool $is_critical
 */
#[Fillable([
    'source_application_id', 'target_application_id', 'protocol', 'port',
    'is_encrypted', 'is_critical', 'frequency', 'description',
])]
class ApplicationFlow extends Model
{
    use HasFactory;

    protected $attributes = ['protocol' => 'other', 'is_critical' => true];

    protected function casts(): array
    {
        return [
            'protocol' => FlowProtocol::class,
            'is_encrypted' => 'boolean',
            'is_critical' => 'boolean',
            'port' => 'integer',
        ];
    }

    /** @return BelongsTo<Application, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'source_application_id');
    }

    /** @return BelongsTo<Application, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'target_application_id');
    }
}
