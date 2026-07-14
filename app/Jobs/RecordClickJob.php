<?php

namespace App\Jobs;

use App\Models\Link;
use App\Services\Links\Callbacks\CallbackDispatcher;
use App\Services\Links\Clicks\ClickRecorder;
use App\Services\Links\Clicks\IgnoredSourceMatcher;
use App\Services\Links\Geo\GeoDatabaseUpdater;
use App\Services\Links\Geo\GeoLocator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecordClickJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly string $clickUuid,
        private readonly int $linkId,
        private readonly int $urlId,
        private readonly ?string $ip,
        private readonly ?string $referrer,
        private readonly ?string $userAgent,
        // Added after this job first shipped. A payload serialized by the older
        // code has no value for these, and constructor defaults do NOT apply on
        // unserialize — the property stays uninitialized. handle() therefore
        // reads them via ?? (isset-safe), never `$this->…` directly.
        private readonly ?string $visitedQuery = null,
        private readonly ?int $ruleVariantId = null,
        // ISO 8601 visit instant captured at redirect time (r43); an older queued
        // payload has none and the click falls back to job-run time.
        private readonly ?string $visitedAt = null,
    ) {}

    public function failed(Throwable $e): void
    {
        report($e);

        Log::warning('Failed to record link click.', [
            'exception' => $e,
            'link_id' => $this->linkId,
            'url_id' => $this->urlId,
            'ip' => $this->ip,
        ]);
    }

    public function handle(
        ClickRecorder $clickRecorder,
        CallbackDispatcher $callbackDispatcher,
        IgnoredSourceMatcher $ignoredSourceMatcher,
        GeoLocator $geoLocator,
        GeoDatabaseUpdater $geoUpdater,
    ): void {
        // Traffic hygiene: a visit from an ignored source (office IP,
        // monitoring) records no click and therefore sends no callback.
        if ($ignoredSourceMatcher->isIgnored($this->ip)) {
            return;
        }

        // withTrashed: the resolve already happened against a live link; if it was
        // soft-deleted before this job ran, the click is still a real historical
        // visit and must be recorded (and its callback delivered).
        $link = Link::withTrashed()->findOrFail($this->linkId);

        // Geolocate the visitor here (recording is already async, the redirect is
        // untouched). locate() never throws — a missing database or an unknown IP
        // simply leaves the click unlocated.
        $geo = $geoLocator->locate($this->ip);

        // ?? (not $this->…): a payload queued by the pre-deploy code leaves these
        // later-added properties uninitialized, and reading such a typed property
        // directly throws. ?? is isset-safe and yields null.
        $visitedQuery = $this->visitedQuery ?? null;
        $ruleVariantId = $this->ruleVariantId ?? null;
        $visitedAt = $this->visitedAt ?? null;

        $click = $clickRecorder->record($link, $this->clickUuid, $this->urlId, $this->ip, $this->referrer, $this->userAgent, $visitedQuery, $ruleVariantId, $geo, $visitedAt);

        $callbackDispatcher->dispatchForClick($click);

        // Keep the geo database fresh "by traffic": a real click occasionally
        // triggers an async age check (throttled, never blocks, never throws).
        $geoUpdater->pingFromTraffic();
    }
}
