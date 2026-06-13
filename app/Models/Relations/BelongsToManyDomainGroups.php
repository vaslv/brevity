<?php

namespace App\Models\Relations;

use App\Models\DomainGroup;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait BelongsToManyDomainGroups
{
    public function domainGroups(): BelongsToMany
    {
        return $this->belongsToMany(DomainGroup::class);
    }
}
