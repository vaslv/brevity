<?php

namespace App\Models\Relations;

use App\Models\LinkRule;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasManyLinkUrls
{
    public function linkUrls(): HasMany
    {
        return $this->hasMany(LinkRule::class);
    }
}
