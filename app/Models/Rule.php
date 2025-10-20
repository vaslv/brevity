<?php

namespace App\Models;

use App\Models\Relations\BelongsToCondition;
use App\Models\Relations\BelongsToLink;
use App\Models\Relations\BelongsToUrl;
use Illuminate\Database\Eloquent\Model;

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
        'priority',
    ];
}
