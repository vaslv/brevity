<?php

namespace App\Models;

use App\Models\Relations\HasManyClicks;
use App\Models\Relations\HasManyRules;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $value
 * @property string $created_at
 * @property-read Collection<int, Click> $clicks
 * @property-read int|null $clicks_count
 * @property-read Collection<int, Rule> $rules
 * @property-read int|null $rules_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Url newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Url newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Url query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Url whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Url whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Url whereValue($value)
 *
 * @mixin \Eloquent
 */
class Url extends Model
{
    use HasFactory;
    use HasManyClicks;
    use HasManyRules;

    public const UPDATED_AT = null;

    public $timestamps = false;

    protected $fillable = [
        'value',
    ];
}
