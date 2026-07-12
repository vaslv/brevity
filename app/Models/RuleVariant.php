<?php

namespace App\Models;

use App\Models\Relations\BelongsToUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A weighted target within a rule's A/B split (GAP-04). A rule with no variants
 * uses its own url_id; with variants the target is chosen by weight.
 *
 * @property int $id
 * @property int $rule_id
 * @property int $url_id
 * @property int $weight
 * @property string|null $label
 * @property-read Url $url
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RuleVariant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RuleVariant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RuleVariant query()
 *
 * @mixin \Eloquent
 */
class RuleVariant extends Model
{
    use BelongsToUrl;
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'rule_id',
        'url_id',
        'weight',
        'label',
    ];
}
