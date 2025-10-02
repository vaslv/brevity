<?php

namespace App\Models\Relations;

use App\Models\IpAddress;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToIpAddress
{
    public function ipAddress(): BelongsTo
    {
        return $this->belongsTo(IpAddress::class);
    }
}
