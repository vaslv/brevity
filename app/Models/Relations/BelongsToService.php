<?php

namespace App\Models\Relations;

use App\Models\Service;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToService
{
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
