<?php

namespace App\Models\Relations;

use App\Models\Link;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasManyLinks
{
    /**
     * @return HasMany<Link, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }
}
