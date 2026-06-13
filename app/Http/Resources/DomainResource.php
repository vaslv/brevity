<?php

namespace App\Http\Resources;

use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Domain $resource
 */
class DomainResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'domain' => $this->resource->value,
            'url' => $this->resource->url,
            'is_default' => $this->resource->is_default,
        ];
    }
}
