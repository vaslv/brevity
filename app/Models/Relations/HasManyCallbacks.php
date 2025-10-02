<?php

namespace App\Models\Relations;

use App\Models\Callback;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasManyCallbacks
{
    public function callbacks(): HasMany
    {
        return $this->hasMany(Callback::class);
    }
}
