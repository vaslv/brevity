<?php

namespace App\Jobs;

use App\Models\Callback;
use App\Services\Links\Callbacks\CallbackStatus;
use App\Services\Links\Callbacks\CallbackUrlGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SendCallbackJob implements ShouldQueue
{
    use Queueable;

    private const int HTTP_TIMEOUT_SECONDS = 10;

    private const int MAX_RESPONSE_BODY_LENGTH = 10_000;

    public int $tries = 5;

    public function __construct(private readonly int $callbackId) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 3600];
    }

    public function failed(Throwable $e): void
    {
        report($e);

        Callback::where('id', $this->callbackId)->update(['status' => CallbackStatus::Failed->value]);

        Log::warning('Callback delivery failed permanently.', [
            'callback_id' => $this->callbackId,
            'exception' => $e->getMessage(),
        ]);
    }

    public function handle(CallbackUrlGuard $urlGuard): void
    {
        $callback = Callback::with('click.link.service')->findOrFail($this->callbackId);

        $callbackUrl = $callback->click->link->service->callback_url;

        if (! $urlGuard->isSafe($callbackUrl)) {
            $this->fail(new \RuntimeException(
                "Callback URL is not safe (blocked by SSRF guard) for callback_id={$this->callbackId}"
            ));

            return;
        }

        $callback->update([
            'attempts' => $callback->attempts + 1,
            'last_attempt_at' => now(),
        ]);

        $response = Http::timeout(self::HTTP_TIMEOUT_SECONDS)
            ->post($callbackUrl, $callback->data);

        $status = $response->status();
        $isClientError = $status >= 400 && $status < 500;
        $isSuccess = $response->successful();
        $isFinal = $isSuccess || $isClientError;

        $callback->update([
            'response_code' => $status,
            'response_body' => Str::limit($response->body(), self::MAX_RESPONSE_BODY_LENGTH, ''),
            'status' => $isSuccess
                ? CallbackStatus::Sent
                : ($isFinal ? CallbackStatus::Failed : CallbackStatus::Pending),
        ]);

        if ($isClientError) {
            $this->fail(new \RuntimeException(
                "Callback HTTP {$status} (client error, not retrying) for callback_id={$this->callbackId}"
            ));

            return;
        }

        if (! $isSuccess) {
            throw new \RuntimeException(
                "Callback HTTP {$status} for callback_id={$this->callbackId}"
            );
        }
    }
}
