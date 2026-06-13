<?php

namespace App\Http\Resources;

use App\Models\DomainGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property DomainGroup $resource
 */
class DomainGroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'domains_count' => $this->whenCounted('domains'),
        ];
    }
}
