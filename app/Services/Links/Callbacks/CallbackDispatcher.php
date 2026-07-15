<?php

namespace App\Services\Links\Callbacks;

use App\Jobs\SendCallbackJob;
use App\Models\Callback;
use App\Models\Click;

readonly class CallbackDispatcher
{
    public function __construct(private CallbackDataRenderer $renderer) {}

    public function dispatchForClick(Click $click): void
    {
        $link = $click->link;

        if ($link->callback_data === null) {
            return;
        }

        $callbackUrl = $link->service->callback_url;

        if ($callbackUrl === null) {
            return;
        }

        $click->loadMissing(['url', 'referrer', 'userAgent', 'ipAddress', 'ruleVariant']);

        $rendered = $this->renderer->render($link->callback_data, $click);

        // Contract (docs/03-api.md §10): every callback carries a root-level
        // `is_bot` flag regardless of the client template, so the partner can
        // discount bot traffic themselves. The key is reserved — a
        // client-supplied `is_bot` is deliberately overridden.
        $rendered['is_bot'] = $click->userAgent->is_bot ?? false;

        // One callback per click: a retried RecordClickJob (same click) must not
        // create a second callback or enqueue a second delivery.
        $callback = Callback::firstOrCreate(
            ['click_id' => $click->id],
            [
                'service_id' => $click->service_id,
                'data' => $rendered,
                'status' => CallbackStatus::Pending,
                'attempts' => 0,
            ]
        );

        if ($callback->wasRecentlyCreated) {
            SendCallbackJob::dispatch($callback->id);
        }
    }
}
