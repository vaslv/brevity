<?php

namespace App\Models;

use App\Models\Relations\HasManyClicks;
use App\Models\Relations\HasManyLinkUrls;
use Illuminate\Database\Eloquent\Model;

class Url extends Model
{
    use HasManyClicks;
    use HasManyLinkUrls;

    public const UPDATED_AT = null;

    public $timestamps = false;

    protected $fillable = [
        'value',
    ];
}
