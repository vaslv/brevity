<?php

namespace App\Models\Relations;

use App\Models\Condition;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait BelongsToManyConditions
{
    public function conditions(): BelongsToMany
    {
        // Deterministic order: the API exposes conditions[] (and the deprecated
        // `condition` = first of them) — without an ORDER BY the row order is
        // whatever the plan returns and may differ between reads.
        return $this->belongsToMany(Condition::class)->orderBy('conditions.id');
    }
}
