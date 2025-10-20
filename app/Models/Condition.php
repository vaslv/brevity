<?php

namespace App\Models;

use App\Models\Relations\HasManyRules;
use Illuminate\Database\Eloquent\Model;

class Condition extends Model
{
    use HasManyRules;

    public const UPDATED_AT = null;

    protected $casts = [
        'value' => 'array',
    ];

    protected $fillable = [
        'type',
        'value',
    ];
}
