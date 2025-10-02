<?php

namespace App\Models\Relations;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToDomain
{
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
