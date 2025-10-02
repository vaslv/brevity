<?php

namespace App\Models;

use App\Models\Relations\HasManyCallbacks;
use App\Models\Relations\HasManyClicks;
use App\Models\Relations\HasManyLinks;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasManyCallbacks;
    use HasManyClicks;
    use HasManyLinks;

    public const UPDATED_AT = null;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'callback_url',
    ];
}
