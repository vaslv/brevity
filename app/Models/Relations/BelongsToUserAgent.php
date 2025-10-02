<?php

namespace App\Models\Relations;

use App\Models\UserAgent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToUserAgent
{
    public function userAgent(): BelongsTo
    {
        return $this->belongsTo(UserAgent::class);
    }
}
