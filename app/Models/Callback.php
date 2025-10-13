<?php

namespace App\Models;

use App\Models\Relations\BelongsToClick;
use App\Models\Relations\BelongsToLink;
use App\Models\Relations\BelongsToService;
use Illuminate\Database\Eloquent\Model;

class Callback extends Model
{
    use BelongsToClick;
    use BelongsToLink;
    use BelongsToService;

    public const UPDATED_AT = null;

    protected $casts = [
        'data' => 'array',
        'last_attempt_at' => 'datetime',
    ];

    protected $fillable = [
        'service_id',
        'click_id',
        'data',
        'response_code',
        'response_body',
        'status',
        'attempts',
        'last_attempt_at',
    ];
}
