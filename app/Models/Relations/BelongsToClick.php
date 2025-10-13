<?php

namespace App\Models\Relations;

use App\Models\Click;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToClick
{
    public function click(): BelongsTo
    {
        return $this->belongsTo(Click::class);
    }
}
