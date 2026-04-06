<?php

namespace App\Models;

use App\Models\Relations\HasManyCallbacks;
use App\Models\Relations\HasManyClicks;
use App\Models\Relations\HasManyLinks;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @property int $id
 * @property string $name
 * @property string|null $callback_url
 * @property string $created_at
 * @property-read Collection<int, Callback> $callbacks
 * @property-read int|null $callbacks_count
 * @property-read Collection<int, Click> $clicks
 * @property-read int|null $clicks_count
 * @property-read Collection<int, Link> $links
 * @property-read int|null $links_count
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereCallbackUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereName($value)
 *
 * @mixin \Eloquent
 */
class Service extends Model
{
    use HasApiTokens;
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
