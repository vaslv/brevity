<?php

namespace App\Models;

use App\Models\Relations\BelongsToClick;
use App\Models\Relations\BelongsToLink;
use App\Models\Relations\BelongsToService;
use App\Services\Links\Callbacks\CallbackStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $service_id
 * @property int $click_id
 * @property array<array-key, mixed> $data
 * @property int|null $response_code
 * @property string|null $response_body
 * @property CallbackStatus $status
 * @property int $attempts
 * @property Carbon|null $last_attempt_at
 * @property Carbon $created_at
 * @property-read Click $click
 * @property-read Link|null $link
 * @property-read Service $service
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Callback newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Callback newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Callback query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Callback whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Callback whereClickId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Callback whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Callback whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Callback whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Callback whereLastAttemptAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Callback whereResponseBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Callback whereResponseCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Callback whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Callback whereStatus($value)
 *
 * @mixin \Eloquent
 */
class Callback extends Model
{
    use BelongsToClick;
    use BelongsToLink;
    use BelongsToService;

    public const UPDATED_AT = null;

    protected $casts = [
        'data' => 'array',
        'last_attempt_at' => 'datetime',
        'status' => CallbackStatus::class,
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
