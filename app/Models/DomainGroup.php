<?php

namespace App\Models;

use App\Models\Relations\BelongsToManyDomains;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Domain> $domains
 * @property-read int|null $domains_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DomainGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DomainGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DomainGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DomainGroup whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DomainGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DomainGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DomainGroup whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DomainGroup whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class DomainGroup extends Model
{
    use BelongsToManyDomains;
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
    ];

    /**
     * Normalise the code to lower case so the API's exact-match `code` lookups
     * (the ?group= filter and domain_group) are never tripped up by casing.
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value === null ? null : Str::lower($value),
        );
    }
}
