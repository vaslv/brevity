<?php

namespace App\Models;

use App\Models\Relations\HasManyClicks;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $country_code
 * @property string $region
 * @property string $city
 * @property string $created_at
 * @property-read Collection<int, Click> $clicks
 * @property-read int|null $clicks_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeoLocation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeoLocation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeoLocation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeoLocation whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeoLocation whereCountryCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeoLocation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeoLocation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeoLocation whereRegion($value)
 *
 * @mixin \Eloquent
 */
class GeoLocation extends Model
{
    use HasFactory;
    use HasManyClicks;

    public const UPDATED_AT = null;

    public $timestamps = false;

    /**
     * Mirror the DB defaults so a tuple created via Eloquent without region/city
     * carries '' (never null — the columns are NOT NULL) before it is persisted.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'region' => '',
        'city' => '',
    ];

    protected $fillable = [
        'country_code',
        'region',
        'city',
    ];
}
