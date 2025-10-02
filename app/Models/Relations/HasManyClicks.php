<?php

namespace App\Models\Relations;

use App\Models\Click;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasManyClicks
{
    public function clicks(): HasMany
    {
        return $this->hasMany(Click::class);
    }
}
