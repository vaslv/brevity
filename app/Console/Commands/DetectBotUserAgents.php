<?php

namespace App\Console\Commands;

use App\Models\UserAgent;
use App\Services\Links\Clicks\BotDetector;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

#[Signature('user-agents:detect-bots')]
#[Description('Re-detect the is_bot flag for every user_agents row. Backfill after deploy and re-run after a pattern-library update.')]
class DetectBotUserAgents extends Command
{
    /**
     * Generous upper bound for one run; released in `finally` as soon as the
     * run ends. Detection is ~sub-ms per row, so even millions of unique UAs
     * finish in minutes. Should a run ever outlive the TTL anyway, the worst
     * case is a second run repeating the same idempotent work — the flags
     * converge either way, no renewal machinery needed.
     */
    private const LOCK_SECONDS = 3600;

    public function handle(BotDetector $botDetector): int
    {
        $lock = Cache::lock('user-agents:detect-bots', self::LOCK_SECONDS);

        if (! $lock->get()) {
            $this->warn('Another user-agents:detect-bots run is in progress; aborting.');

            return self::FAILURE;
        }

        try {
            $changed = $this->detect($botDetector);
        } finally {
            $lock->release();
        }

        $this->info("Re-detected user agents; {$changed} flag(s) changed.");

        // The bot split in link_click_counters was snapshotted at click time;
        // flipped flags leave it stale until the counters are rebuilt.
        if ($changed > 0) {
            $this->warn('Bot flags changed — run clicks:rebuild-counters to refresh the counter split.');
        }

        return self::SUCCESS;
    }

    private function detect(BotDetector $botDetector): int
    {
        $changed = 0;

        UserAgent::query()
            ->orderBy('id')
            ->chunkById(500, function (Collection $userAgents) use ($botDetector, &$changed): void {
                $flip = ['flag' => [], 'unflag' => []];

                /** @var UserAgent $userAgent */
                foreach ($userAgents as $userAgent) {
                    $isBot = $botDetector->isBot($userAgent->value);

                    if ($isBot !== $userAgent->is_bot) {
                        $flip[$isBot ? 'flag' : 'unflag'][] = $userAgent->id;
                    }
                }

                // Batch per chunk: only rows whose flag actually flips are written.
                foreach (['flag' => true, 'unflag' => false] as $key => $value) {
                    if ($flip[$key] !== []) {
                        UserAgent::query()->whereIn('id', $flip[$key])->update(['is_bot' => $value]);
                        $changed += count($flip[$key]);
                    }
                }
            });

        return $changed;
    }
}
