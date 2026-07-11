<?php

namespace App\Services\Links\Clicks;

use App\Models\LinkClickCounter;
use Illuminate\Support\Facades\DB;

readonly class ClickCounterIncrementer
{
    /**
     * 100 slots spread concurrent increments across rows: parallel recording
     * for one link lands on random slots instead of contending on a hot row.
     */
    public const int SLOTS = 100;

    public function increment(int $linkId, bool $isBot): void
    {
        LinkClickCounter::query()->upsert(
            [[
                'link_id' => $linkId,
                'is_bot' => $isBot,
                'slot' => random_int(1, self::SLOTS),
                'count' => 1,
            ]],
            ['link_id', 'is_bot', 'slot'],
            ['count' => DB::raw('link_click_counters.count + 1')],
        );
    }
}
