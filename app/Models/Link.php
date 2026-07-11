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
        'deleted_at' => 'datetime',
    ];

    protected $fillable = [
        'service_id',
        'domain_id',
        'code',
        'title',
        'forward_query',
        'callback_data',
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
