<?php

namespace App\Models;

use App\Models\Relations\BelongsToDomain;
use App\Models\Relations\BelongsToService;
use App\Models\Relations\HasManyClickCounters;
use App\Models\Relations\HasManyClicks;
use App\Models\Relations\HasManyRules;
use App\Services\Links\CodeStrategy\CodeGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use League\Uri\Uri;

/**
 * @property int $id
 * @property int $service_id
 * @property int|null $domain_id
 * @property string|null $code
 * @property string|null $title
 * @property bool $forward_query
 * @property array<array-key, mixed>|null $callback_data
 * @property Carbon|null $valid_since
 * @property Carbon|null $valid_until
 * @property int|null $max_clicks
 * @property Carbon $created_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Click> $clicks
 * @property-read int|null $clicks_count
 * @property-read Collection<int, LinkClickCounter> $clickCounters
 * @property-read int|null $click_counters_count
 * @property-read Domain|null $domain
 * @property-read string $url
 * @property-read Collection<int, Rule> $rules
 * @property-read int|null $rules_count
 * @property-read Service $service
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereCallbackData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereDomainId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereForwardQuery($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Link extends Model
{
    use BelongsToDomain;
    use BelongsToService;
    use HasFactory;
    use HasManyClickCounters;
    use HasManyClicks;
    use HasManyRules;
    use SoftDeletes;

    public const UPDATED_AT = null;

    protected $casts = [
        'forward_query' => 'boolean',
        'callback_data' => 'array',
        'valid_since' => 'datetime',
        'valid_until' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $fillable = [
        'service_id',
        'domain_id',
        'code',
        'title',
        'forward_query',
        'callback_data',
        'valid_since',
        'valid_until',
        'max_clicks',
    ];

    public static function findByCode(string $code): ?Link
    {
        return static::where('code', $code)->first();
    }

    public function getUrlAttribute(): string
    {
        $base = $this->domain?->url ?? config('app.url');

        return Uri::new($base)
            ->withPath('/'.$this->code)
            ->toString();
    }

    /**
     * Lifecycle check (docs/07-plans.md §4): alive only while valid_since is
     * not in the future, valid_until is not in the past, and the counter sum
     * (all clicks, bots included — decision 2026-07-12) stays below
     * max_clicks. The counter query runs only for limited links; async click
     * recording makes the limit slightly soft (bounded by queue lag).
     */
    public function isAlive(\DateTimeInterface $now): bool
    {
        if ($this->valid_since !== null && $this->valid_since->greaterThan($now)) {
            return false;
        }

        if ($this->valid_until !== null && $this->valid_until->lessThan($now)) {
            return false;
        }

        if ($this->max_clicks !== null
            && (int) $this->clickCounters()->sum('count') >= $this->max_clicks) {
            return false;
        }

        return true;
    }

    protected static function booted(): void
    {
        static::created(function (Link $link) {
            /** @var CodeGenerator $generator */
            $generator = app(CodeGenerator::class);

            $link->updateQuietly([
                'code' => $generator->generate($link),
            ]);
        });
    }
}
