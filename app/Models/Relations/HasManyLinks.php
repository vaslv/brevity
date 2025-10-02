<?php

namespace App\Models\Relations;

use App\Models\Link;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasManyLinks
{
    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }
}
