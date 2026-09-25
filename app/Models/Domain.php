<?php

namespace App\Models;

use App\Models\Relations\BelongsToManyDomainGroups;
use App\Models\Relations\HasManyLinks;
use App\Services\Links\Domains\DomainName;
use Illuminate\Database\Eloquent\Builder;
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
 * @property-read string $display_domain
 * @property-read Collection<int, DomainGroup> $domainGroups
 * @property-read int|null $domain_groups_count
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
    use BelongsToManyDomainGroups;
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

    public function getDisplayDomainAttribute(): string
    {
        return DomainName::toUnicode($this->value);
    }

    public function getUrlAttribute(): string
    {
        return Uri::new()
            ->withHost($this->value)
            ->withScheme('https')
            ->toString();
    }

    /**
     * Search ASCII fragments or a complete domain in either representation.
     * Unicode fragments are not equivalent to fragments of their Punycode.
     *
     * @param  Builder<Domain>  $query
     * @return Builder<Domain>
     */
    public function scopeMatchingName(Builder $query, string $search): Builder
    {
        $ascii = DomainName::tryToAscii($search);

        return $query->where(function (Builder $query) use ($search, $ascii): void {
            $query->where('value', 'ilike', '%'.trim($search).'%');

            if ($ascii !== null) {
                $query->orWhere('value', $ascii);
            }
        });
    }

    public function setValueAttribute(string $value): void
    {
        $this->attributes['value'] = DomainName::toAscii($value);
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
