<?php

namespace App\Models;

use App\Models\Relations\HasManyClicks;
use Illuminate\Database\Eloquent\Model;

class UserAgent extends Model
{
    use HasManyClicks;

    public const UPDATED_AT = null;

    protected $fillable = [
        'value',
    ];
}
