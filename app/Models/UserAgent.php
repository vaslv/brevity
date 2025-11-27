<?php

namespace App\Models;

use App\Models\Relations\HasManyClicks;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $value
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Click> $clicks
 * @property-read int|null $clicks_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAgent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAgent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAgent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAgent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAgent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAgent whereValue($value)
 *
 * @mixin \Eloquent
 */
class UserAgent extends Model
{
    use HasManyClicks;

    public const UPDATED_AT = null;

    protected $fillable = [
        'value',
    ];
}
