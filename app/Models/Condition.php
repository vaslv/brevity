<?php

namespace App\Models;

use App\Models\Relations\HasManyLinkUrls;
use Illuminate\Database\Eloquent\Model;

class Condition extends Model
{
    use HasManyLinkUrls;

    public const UPDATED_AT = null;

    protected $casts = [
        'value' => 'array',
    ];

    protected $fillable = [
        'type',
        'value',
    ];
}
