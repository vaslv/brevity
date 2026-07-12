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
        $conditions = $this->resource->conditions;

        return [
            'url' => $this->resource->url->value,
            'conditions' => ConditionResource::collection($conditions),
            // Deprecated (RUL-01): the single `condition` field is kept for
            // legacy clients — the first condition, or null when unconditional.
            // New clients read the `conditions` array.
            'condition' => $conditions->isNotEmpty()
                ? ConditionResource::make($conditions->first())
                : null,
            'transition_mode' => $this->resource->transition_mode,
        ];
    }
}
