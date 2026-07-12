<?php

namespace App\Models\Relations;

use App\Models\GeoLocation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToGeoLocation
{
    public function geoLocation(): BelongsTo
    {
        return $this->belongsTo(GeoLocation::class);
    }
}
