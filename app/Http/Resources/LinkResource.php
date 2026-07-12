<?php

namespace App\Http\Resources;

use App\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Link $resource
 */
class LinkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'url' => $this->resource->url,
            'domain' => $this->resource->domain->value ?? null,
            'code' => $this->resource->code,
            'title' => $this->resource->title,
            'forward_query' => $this->resource->forward_query,
            'callback_data' => $this->resource->callback_data,
            'valid_since' => $this->resource->valid_since?->toIso8601String(),
            'valid_until' => $this->resource->valid_until?->toIso8601String(),
            'max_clicks' => $this->resource->max_clicks,
            'rules' => RuleResource::collection($this->resource->rules),
        ];
    }
}
