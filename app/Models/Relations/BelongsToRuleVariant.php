<?php

namespace App\Models\Relations;

use App\Models\RuleVariant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToRuleVariant
{
    public function ruleVariant(): BelongsTo
    {
        return $this->belongsTo(RuleVariant::class);
    }
}
