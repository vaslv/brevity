<?php

namespace App\Models;

use App\Models\Relations\HasManyClicks;
use Illuminate\Database\Eloquent\Model;

class Referrer extends Model
{
    use HasManyClicks;

    public const UPDATED_AT = null;

    public $timestamps = false;

    protected $fillable = [
        'value',
    ];
}
