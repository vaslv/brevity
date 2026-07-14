<?php

namespace App\Models;

use App\Models\Relations\BelongsToGeoLocation;
use App\Models\Relations\BelongsToIpAddress;
use App\Models\Relations\BelongsToLink;
use App\Models\Relations\BelongsToReferrer;
use App\Models\Relations\BelongsToRuleVariant;
use App\Models\Relations\BelongsToService;
use App\Models\Relations\BelongsToUrl;
use App\Models\Relations\BelongsToUserAgent;
use App\Models\Relations\HasManyCallbacks;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $service_id
 * @property int $link_id
 * @property int $url_id
 * @property int|null $referrer_id
 * @property int|null $user_agent_id
 * @property int|null $ip_address_id
 * @property int|null $rule_variant_id
 * @property int|null $geo_location_id
 * @property string|null $visited_query
 * @property Carbon $created_at
 * @property-read Collection<int, Callback> $callbacks
 * @property-read int|null $callbacks_count
 * @property-read GeoLocation|null $geoLocation
 * @property-read IpAddress|null $ipAddress
 * @property-read Link $link
 * @property-read Referrer|null $referrer
 * @property-read RuleVariant|null $ruleVariant
 * @property-read Service $service
 * @property-read Url $url
 * @property-read UserAgent|null $userAgent
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click whereIpAddressId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click whereLinkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click whereReferrerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click whereUrlId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Click whereUserAgentId($value)
 *
 * @mixin \Eloquent
 */
class Click extends Model
{
    use BelongsToGeoLocation;
    use BelongsToIpAddress;
    use BelongsToLink;
    use BelongsToReferrer;
    use BelongsToRuleVariant;
    use BelongsToService;
    use BelongsToUrl;
    use BelongsToUserAgent;
    use HasFactory;
    use HasManyCallbacks;

    public const UPDATED_AT = null;

    protected $fillable = [
        'uuid',
        'service_id',
        'link_id',
        'url_id',
        'referrer_id',
        'user_agent_id',
        'ip_address_id',
        'rule_variant_id',
        'geo_location_id',
        'visited_query',
        // Set explicitly to the visit instant captured at redirect time, not the
        // async job's run time (r43). Clicks are only ever created server-side by
        // ClickRecorder, so this carries no mass-assignment surface.
        'created_at',
    ];
}
