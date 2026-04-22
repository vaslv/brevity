<?php

namespace App\Jobs;

use App\Models\Link;
use App\Services\Links\Callbacks\CallbackDispatcher;
use App\Services\Links\Clicks\ClickRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecordClickJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
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

    public function handle(ClickRecorder $clickRecorder, CallbackDispatcher $callbackDispatcher): void
    {
        $link = Link::findOrFail($this->linkId);

        $click = $clickRecorder->record($link, $this->urlId, $this->ip, $this->referrer, $this->userAgent);

        $callbackDispatcher->dispatchForClick($click);
    }
}
