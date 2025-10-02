<?php

namespace App\Models;

use App\Models\Relations\BelongsToDomain;
use App\Models\Relations\BelongsToService;
use App\Models\Relations\HasManyClicks;
use App\Models\Relations\HasManyLinkUrls;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Link extends Model
{
    use BelongsToDomain;
    use BelongsToService;
    use HasManyClicks;
    use HasManyLinkUrls;
    use SoftDeletes;

    public const UPDATED_AT = null;

    protected $casts = [
        'forward_query' => 'boolean',
        'callback_data' => 'array',
        'deleted_at' => 'datetime',
    ];

    protected $fillable = [
        'service_id',
        'domain_id',
        'code',
        'title',
        'forward_query',
        'callback_data',
    ];
}
