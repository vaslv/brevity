<?php

namespace App\Models\Relations;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait BelongsToManyDomains
{
    /**
     * @return BelongsToMany<Domain, $this>
     */
    public function domains(): BelongsToMany
    {
        return $this->belongsToMany(Domain::class);
    }
}
