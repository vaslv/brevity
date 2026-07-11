<?php

namespace App\Models;

use App\Models\Relations\BelongsToLink;
use Illuminate\Database\Eloquent\Model;

/**
 * Slotted pre-aggregated click counter (docs/07-plans.md §3): a link's click
 * total is the SUM of `count` over its slots. Rows change only inside the
 * click-recording transaction or via clicks:rebuild-counters.
 *
 * @property int $id
 * @property int $link_id
 * @property bool $is_bot
 * @property int $slot
 * @property int $count
 * @property-read Link $link
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkClickCounter newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkClickCounter newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LinkClickCounter query()
 *
 * @mixin \Eloquent
 */
class LinkClickCounter extends Model
{
    use BelongsToLink;

    public $timestamps = false;

    protected $casts = [
        'is_bot' => 'boolean',
    ];

    protected $fillable = [
        'link_id',
        'is_bot',
        'slot',
        'count',
    ];
}
