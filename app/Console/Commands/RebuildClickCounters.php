<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

#[Signature('clicks:rebuild-counters')]
#[Description('Rebuild link_click_counters from the clicks table. Initial backfill after deploy and reconciliation after bulk click changes.')]
class RebuildClickCounters extends Command
{
    private const LOCK_SECONDS = 3600;

    public function handle(): int
    {
        $lock = Cache::lock('clicks:rebuild-counters', self::LOCK_SECONDS);

        if (! $lock->get()) {
            $this->warn('Another clicks:rebuild-counters run is in progress; aborting.');

            return self::FAILURE;
        }

        try {
            $rows = $this->rebuild();
        } finally {
            $lock->release();
        }

        $this->info("Rebuilt click counters: {$rows} row(s).");

        return self::SUCCESS;
    }

    /**
     * TRUNCATE + one aggregate INSERT..SELECT inside a single transaction: the
     * table is either the old or the new consistent state, never in between.
     * Slot is always 1 on rebuild — slot spreading only matters for live
     * concurrent increments. Clicks without a user agent count as non-bot.
     * Live increments landing during the rebuild wait on the table lock and
     * apply on top of the fresh aggregate afterwards.
     */
    private function rebuild(): int
    {
        return DB::transaction(function (): int {
            DB::statement('truncate table link_click_counters');

            $inserted = DB::affectingStatement(<<<'SQL'
                insert into link_click_counters (link_id, is_bot, slot, count)
                select
                    clicks.link_id,
                    coalesce(user_agents.is_bot, false),
                    1,
                    count(*)
                from clicks
                left join user_agents on user_agents.id = clicks.user_agent_id
                group by clicks.link_id, coalesce(user_agents.is_bot, false)
                SQL);

            return $inserted;
        });
    }
}
