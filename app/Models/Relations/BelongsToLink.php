<?php

namespace App\Models\Relations;

use App\Models\Link;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToLink
{
    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class);
    }
}
