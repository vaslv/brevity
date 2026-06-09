<?php

namespace App\Models;

use App\Models\Relations\HasManyLinks;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use League\Uri\Uri;

/**
 * @property int $id
 * @property string $value
 * @property bool $is_default
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Domain whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Domain whereValue($value)
 *
 * @mixin \Eloquent
 */
class Domain extends Model
{
    use HasFactory;
    use HasManyLinks;

    public const UPDATED_AT = null;

    public $timestamps = false;

    protected $appends = [
        'url',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected $fillable = [
        'value',
        'is_default',
    ];

    /**
     * The domain used when a link is created without an explicit domain.
     */
    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }

    public function getUrlAttribute(): string
    {
        return Uri::new()
            ->withHost($this->value)
            ->withScheme('https')
            ->toString();
    }

    protected static function booted(): void
    {
        // Keep the single-default invariant at the application level: promoting
        // a domain demotes the previous default before this row is written, so
        // the partial unique index is never violated under normal use.
        static::saving(function (Domain $domain) {
            if ($domain->is_default && $domain->isDirty('is_default')) {
                static::query()
                    ->where('is_default', true)
                    ->when($domain->exists, fn ($query) => $query->whereKeyNot($domain->getKey()))
                    ->update(['is_default' => false]);
            }
        });
    }
}
