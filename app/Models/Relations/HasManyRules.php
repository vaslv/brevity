<?php

namespace App\Models\Relations;

use App\Models\Rule;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasManyRules
{
    public function rules(): HasMany
    {
        return $this->hasMany(Rule::class);
    }
}
