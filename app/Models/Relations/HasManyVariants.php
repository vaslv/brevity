<?php

namespace App\Models\Relations;

use App\Models\RuleVariant;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasManyVariants
{
    /**
     * @return HasMany<RuleVariant, $this>
     */
    public function variants(): HasMany
    {
        // Ordered by id so the weighted pick walks variants deterministically.
        return $this->hasMany(RuleVariant::class)->orderBy('id');
    }
}
