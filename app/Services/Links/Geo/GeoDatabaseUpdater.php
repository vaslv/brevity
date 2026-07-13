<?php

namespace App\Services\Links\Geo;

use App\Jobs\UpdateGeoDatabaseJob;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps the geo database fresh "by traffic" (stage 4): a click pings this, and
 * when the database has aged past geo.max_age_days a download job is dispatched.
 * No cron. A throttle bounds the check to once an interval regardless of traffic
 * volume, and a consecutive-failure backoff stops hammering MaxMind when
 * downloads keep failing.
 */
class GeoDatabaseUpdater
{
    // How long the failure/last-failure counters survive on their own, so the
    // backoff self-clears if traffic dries up before a successful refresh.
    private const FAILURE_STATE_TTL_DAYS = 7;

    // Retry a few times before backing off — a single failed download may be a
    // transient network blip, a run of them is a real problem (bad key, outage).
    private const FAILURE_THRESHOLD = 3;

    private const FAILURES_KEY = 'geo:update:failures';

    private const LAST_FAILURE_KEY = 'geo:update:last-failure';

    private const THROTTLE_KEY = 'geo:update:throttle';

    private const THROTTLE_SECONDS = 3600;

    public function __construct(private GeoDatabaseDownloader $downloader) {}

    /**
     * Cheap, throttled entry point for the click job. Dispatches the download
     * job only when a refresh is actually due, so it never touches MaxMind more
     * than needed. Never throws — geo must not affect click recording.
     */
    public function pingFromTraffic(): void
    {
        try {
            // First click of the interval wins; the rest short-circuit here so
            // every click stays O(1) regardless of traffic. The throttle bounds
            // the check even when nothing is due (fresh database, backoff), so a
            // refresh can start up to one interval after it becomes due — fine at
            // a 30-day refresh cadence.
            if (! Cache::add(self::THROTTLE_KEY, true, self::THROTTLE_SECONDS)) {
                return;
            }

            if ($this->shouldRefresh()) {
                UpdateGeoDatabaseJob::dispatch();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function refreshIfStale(): void
    {
        // Re-check under the job (state may have changed since the ping).
        if (! $this->shouldRefresh()) {
            return;
        }

        $this->recordOutcome($this->downloader->download());
    }

    public function shouldRefresh(): bool
    {
        return $this->downloader->isConfigured() && $this->isStale() && ! $this->inBackoff();
    }

    private function inBackoff(): bool
    {
        if ((int) Cache::get(self::FAILURES_KEY, 0) < self::FAILURE_THRESHOLD) {
            return false;
        }

        $backoffSeconds = (int) config('geo.download_backoff_minutes') * 60;

        return (int) Cache::get(self::LAST_FAILURE_KEY, 0) + $backoffSeconds > now()->timestamp;
    }

    private function isStale(): bool
    {
        $path = (string) config('geo.database_path');

        // clearstatcache: under long-running Octane the stat cache can outlive a
        // just-installed database. @filemtime with a === false guard treats a
        // missing/unreadable file (including the TOCTOU window where it vanishes
        // between checks) as stale rather than raising a warning the job would
        // surface as a failure.
        clearstatcache(true, $path);
        $modifiedAt = @filemtime($path);

        if ($modifiedAt === false) {
            return true;
        }

        return $modifiedAt < now()->subDays((int) config('geo.max_age_days'))->timestamp;
    }

    private function recordOutcome(GeoDownloadResult $result): void
    {
        if ($result->succeeded()) {
            Cache::forget(self::FAILURES_KEY);
            Cache::forget(self::LAST_FAILURE_KEY);

            return;
        }

        // A Skipped (lock held) or NotConfigured outcome is not a download
        // failure — leave the backoff counters untouched.
        if ($result->status === GeoDownloadStatus::Failed) {
            $failures = (int) Cache::get(self::FAILURES_KEY, 0) + 1;
            Cache::put(self::FAILURES_KEY, $failures, now()->addDays(self::FAILURE_STATE_TTL_DAYS));
            Cache::put(self::LAST_FAILURE_KEY, now()->timestamp, now()->addDays(self::FAILURE_STATE_TTL_DAYS));
        }
    }
}
