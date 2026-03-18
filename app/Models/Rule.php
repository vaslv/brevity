<?php

namespace App\Models;

use App\Models\Relations\BelongsToCondition;
use App\Models\Relations\BelongsToLink;
use App\Models\Relations\BelongsToUrl;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $link_id
 * @property int $url_id
 * @property int|null $condition_id
 * @property string|null $transition_mode
 * @property int $priority
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\Condition|null $condition
 * @property-read \App\Models\Link $link
 * @property-read \App\Models\Url $url
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule whereConditionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule whereLinkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule whereTransitionMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule whereUrlId($value)
 *
 * @mixin \Eloquent
 */
class Rule extends Model
{
    use BelongsToCondition;
    use BelongsToLink;
    use BelongsToUrl;

    public const UPDATED_AT = null;

    protected $fillable = [
        'link_id',
        'url_id',
        'condition_id',
        'transition_mode',
        'priority',
    ];
}
