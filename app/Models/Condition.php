<?php

namespace App\Models;

use App\Models\Relations\HasManyRules;
use Illuminate\Database\Eloquent\Model;

class Condition extends Model
{
    use HasManyRules;

    public const UPDATED_AT = null;

    protected $casts = [
        'data' => 'array',
    ];

    protected $fillable = [
        'type',
        'data',
    ];
}
