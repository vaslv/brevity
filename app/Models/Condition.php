<?php

namespace App\Models;

use App\Models\Relations\HasManyRules;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    use HasFactory;
    use HasManyRules;

    public const UPDATED_AT = null;

    public $timestamps = false;

    protected $casts = [
        'data' => 'array',
    ];

    protected $fillable = [
        'type',
        'data',
    ];

    public function describe(): string
    {
        return match ($this->type) {
            'time_before' => __('resources/condition.describe.time_before', [
                'datetime' => isset($this->data['before'])
                    ? Carbon::parse($this->data['before'])->isoFormat('D MMM YYYY, HH:mm')
                    : '—',
            ]),
            'after_date' => __('resources/condition.describe.after_date', [
                'datetime' => isset($this->data['after'])
                    ? Carbon::parse($this->data['after'])->isoFormat('D MMM YYYY, HH:mm')
                    : '—',
            ]),
            'query_param' => __('resources/condition.describe.query_param', [
                'key' => $this->data['key'] ?? '—',
                'value' => $this->data['value'] ?? '—',
            ]),
            default => __('resources/condition.types.'.$this->type),
        };
    }
}
