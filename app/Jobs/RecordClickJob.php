<?php

namespace App\Jobs;

use App\Models\Link;
use App\Services\Links\Callbacks\CallbackDispatcher;
use App\Services\Links\Clicks\ClickRecorder;
use App\Services\Links\Clicks\IgnoredSourceMatcher;
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

        $click = $clickRecorder->record($link, $this->clickUuid, $this->urlId, $this->ip, $this->referrer, $this->userAgent);

        $callbackDispatcher->dispatchForClick($click);
    }
}
