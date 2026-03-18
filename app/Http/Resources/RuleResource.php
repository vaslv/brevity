<?php

namespace App\Http\Resources;

use App\Models\Rule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Rule $resource
 */
class RuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'url' => $this->resource->url->value,
            'condition' => ConditionResource::make($this->resource->condition),
            'transition_mode' => $this->resource->transition_mode,
        ];
    }
}
