<?php

namespace App\Models\Relations;

use App\Models\Condition;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait BelongsToManyConditions
{
    public function conditions(): BelongsToMany
    {
        return $this->belongsToMany(Condition::class);
    }
}
