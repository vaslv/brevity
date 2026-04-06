<?php

namespace App\Models;

use App\Models\Relations\HasManyRules;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property array<array-key, mixed> $data
 * @property Carbon $created_at
 * @property-read Collection<int, Rule> $rules
 * @property-read int|null $rules_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Condition newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Condition newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Condition query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Condition whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Condition whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Condition whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Condition whereType($value)
 *
 * @mixin \Eloquent
 */
class Condition extends Model
{
    use HasManyRules;

    public const UPDATED_AT = null;

    protected $casts = [
        'data' => 'array',
    ];

    protected $fillable = [
        'type',
        'data',
    ];
}
