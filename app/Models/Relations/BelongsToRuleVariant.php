<?php

namespace App\Models\Relations;

use App\Models\RuleVariant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToRuleVariant
{
    /**
     * @return BelongsTo<RuleVariant, $this>
     */
    public function ruleVariant(): BelongsTo
    {
        return $this->belongsTo(RuleVariant::class);
    }
}
