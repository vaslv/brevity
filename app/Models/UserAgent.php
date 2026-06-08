<?php

namespace App\Models;

use App\Models\Relations\HasManyClicks;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $value
 * @property Carbon $created_at
 * @property-read Collection<int, Click> $clicks
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

    public $timestamps = false;

    protected $fillable = [
        'value',
    ];
}
