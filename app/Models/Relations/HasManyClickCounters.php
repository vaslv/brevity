<?php

namespace App\Models\Relations;

use App\Models\LinkClickCounter;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasManyClickCounters
{
    public function clickCounters(): HasMany
    {
        return $this->hasMany(LinkClickCounter::class);
    }
}
