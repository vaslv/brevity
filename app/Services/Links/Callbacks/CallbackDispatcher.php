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

        $click->loadMissing(['url', 'referrer', 'userAgent', 'ipAddress']);

        $rendered = $this->renderer->render($link->callback_data, $click);

        $callback = Callback::create([
            'service_id' => $click->service_id,
            'click_id' => $click->id,
            'data' => $rendered,
            'status' => CallbackStatus::Pending,
            'attempts' => 0,
        ]);

        SendCallbackJob::dispatch($callback->id);
    }
}
