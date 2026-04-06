<?php

namespace App\Models;

use App\Models\Relations\HasManyLinks;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use League\Uri\Uri;

/**
 * @property int $id
 * @property string $value
 * @property Carbon $created_at
 * @property-read string $url
 * @property-read Collection<int, Link> $links
 * @property-read int|null $links_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Domain newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Domain newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Domain query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Domain whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Domain whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Domain whereValue($value)
 *
 * @mixin \Eloquent
 */
class Domain extends Model
{
    use HasManyLinks;

    public const UPDATED_AT = null;

    protected $appends = [
        'url',
    ];

    protected $fillable = [
        'value',
    ];

    public function getUrlAttribute(): string
    {
        return Uri::new()
            ->withHost($this->value)
            ->withScheme('https')
            ->toString();
    }
}
