<?php

namespace App\Console\Commands;

use App\Jobs\SendCallbackJob;
use App\Models\Callback;
use App\Services\Links\Callbacks\CallbackStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('callbacks:redispatch-stale')]
#[Description('Re-dispatch Pending callbacks whose delivery job was lost (stale beyond the retry window).')]
class RedispatchStaleCallbacks extends Command
{
    /**
     * A callback stays Pending only while its SendCallbackJob is in flight or in
     * backoff. The full retry chain spans ~81 min (tries=5, backoff 1m/5m/15m/1h),
     * so anything still Pending beyond this threshold has lost its job (e.g. a
     * crash between the row commit and the Redis enqueue) and is safe to
     * redispatch without racing an in-flight delivery.
     */
    private const STALE_AFTER_HOURS = 2;

    public function handle(): int
    {
        $cutoff = now()->subHours(self::STALE_AFTER_HOURS);
        $count = 0;

        Callback::query()
            ->where('status', CallbackStatus::Pending->value)
            ->whereRaw('coalesce(last_attempt_at, created_at) < ?', [$cutoff])
            ->orderBy('id')
            ->chunkById(500, function (Collection $callbacks) use (&$count): void {
                /** @var Callback $callback */
                foreach ($callbacks as $callback) {
                    SendCallbackJob::dispatch($callback->id);
                    $count++;
                }
            });

        $this->info("Re-dispatched {$count} stale pending callback(s).");

        return self::SUCCESS;
    }
}
