<?php

namespace App\Models;

use App\Models\Relations\HasManyClicks;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $value
 * @property string $created_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Click> $clicks
 * @property-read int|null $clicks_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Referrer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Referrer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Referrer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Referrer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Referrer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Referrer whereValue($value)
 *
 * @mixin \Eloquent
 */
class Referrer extends Model
{
    use HasManyClicks;

    public const UPDATED_AT = null;

    public $timestamps = false;

    protected $fillable = [
        'value',
    ];
}
