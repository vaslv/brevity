<?php

namespace App\Models\Relations;

use App\Models\Condition;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCondition
{
    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }
}
