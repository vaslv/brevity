<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * The POST-response shape plus a click summary from the slot counters
 * (docs/03-api.md §5.1). Expects `clickCounters` to be eager-loaded.
 */
class LinkWithStatsResource extends LinkResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $counters = $this->resource->clickCounters;
        $total = (int) $counters->sum('count');
        $bots = (int) $counters->where('is_bot', true)->sum('count');

        return array_merge(parent::toArray($request), [
            'clicks' => [
                'total' => $total,
                'non_bots' => $total - $bots,
            ],
        ]);
    }
}
