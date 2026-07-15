<?php

namespace App\Models\Relations;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToDomain
{
    /**
     * @return BelongsTo<Domain, $this>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
