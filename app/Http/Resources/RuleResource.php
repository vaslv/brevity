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
            // Guard against a null condition: a nested JsonResource::make(null)
            // does NOT collapse to null, it serializes as {"type":null,"data":null}.
            // docs/03-api.md guarantees `condition` is null for an unconditional rule.
            'condition' => $this->resource->condition
                ? ConditionResource::make($this->resource->condition)
                : null,
            'transition_mode' => $this->resource->transition_mode,
        ];
    }
}
