<?php

namespace App\Http\Resources;

use App\Models\Technology;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Technology */
class TechnologyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label(),
            'category' => EnumValue::of($this->category),
            'end_of_support_at' => $this->end_of_support_at?->toDateString(),
            'end_of_life_at' => $this->end_of_life_at?->toDateString(),
            'obsolete' => $this->isObsolete(),
        ];
    }
}
