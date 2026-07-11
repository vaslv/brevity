<?php

namespace App\Models\Relations;

use App\Models\Link;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToLink
{
    public function link(): BelongsTo
    {
        // Include trashed links: clicks/callbacks/rules are historical facts that
        // must outlive a soft-deleted ("disabled") link (see docs/01-architecture.md).
        // Without this the async pipeline loses clicks and crashes callbacks once
        // a link is soft-deleted between dispatch and execution.
        return $this->belongsTo(Link::class)->withTrashed();
    }
}
