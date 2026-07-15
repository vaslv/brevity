<?php

namespace App\Models\Relations;

use App\Models\Rule;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasManyRules
{
    /**
     * @return HasMany<Rule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(Rule::class)
            ->orderBy('priority')
            ->orderBy('id');
    }
}
