<?php

namespace App\Models\Relations;

use App\Models\Url;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToUrl
{
    /**
     * @return BelongsTo<Url, $this>
     */
    public function url(): BelongsTo
    {
        return $this->belongsTo(Url::class);
    }
}
