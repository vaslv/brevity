<?php

namespace App\Models;

use App\Models\Relations\BelongsToIpAddress;
use App\Models\Relations\BelongsToLink;
use App\Models\Relations\BelongsToReferrer;
use App\Models\Relations\BelongsToService;
use App\Models\Relations\BelongsToUrl;
use App\Models\Relations\BelongsToUserAgent;
use App\Models\Relations\HasManyCallbacks;
use Illuminate\Database\Eloquent\Model;

class Click extends Model
{
    use BelongsToIpAddress;
    use BelongsToLink;
    use BelongsToReferrer;
    use BelongsToService;
    use BelongsToUrl;
    use BelongsToUserAgent;
    use HasManyCallbacks;

    public const UPDATED_AT = null;

    protected $fillable = [
        'service_id',
        'link_id',
        'url_id',
        'referrer_id',
        'user_agent_id',
        'ip_address_id',
    ];
}
