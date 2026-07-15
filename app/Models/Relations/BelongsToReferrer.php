<?php

namespace App\Models\Relations;

use App\Models\Referrer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToReferrer
{
    /**
     * @return BelongsTo<Referrer, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Referrer::class);
    }
}
