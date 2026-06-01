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
     * Delays between the 5 attempts (4 gaps): 1m, 5m, 15m, 1h.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function failed(Throwable $e): void
    {
        report($e);

        Callback::where('id', $this->callbackId)->update(['status' => CallbackStatus::Failed->value]);

        Log::warning('Callback delivery failed permanently.', [
            'callback_id' => $this->callbackId,
            'exception' => $e,
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

        // Do not follow redirects: the send-time SSRF guard validated only the
        // configured callback_url. A 3xx response could point Guzzle at an
        // internal host (e.g. 169.254.169.254) that was never checked.
        $response = Http::timeout(self::HTTP_TIMEOUT_SECONDS)
            ->withOptions(['allow_redirects' => false])
            ->post($callbackUrl, $callback->data);

        $status = $response->status();
        $isSuccess = $response->successful();
        // 3xx (unfollowed) and 4xx are permanent, non-retryable failures; only
        // 5xx / network errors are retried.
        $isPermanentFailure = $status >= 300 && $status < 500;

        $callback->update([
            'response_code' => $status,
            'response_body' => $this->sanitizeResponseBody($response->body()),
            'status' => $isSuccess
                ? CallbackStatus::Sent
                : ($isPermanentFailure ? CallbackStatus::Failed : CallbackStatus::Pending),
        ]);

        if ($isPermanentFailure) {
            $this->fail(new \RuntimeException(
                "Callback HTTP {$status} (permanent, not retrying) for callback_id={$this->callbackId}"
            ));

            return;
        }

        if (! $isSuccess) {
            throw new \RuntimeException(
                "Callback HTTP {$status} for callback_id={$this->callbackId}"
            );
        }
    }

    private function sanitizeResponseBody(string $body): string
    {
        $scrubbed = mb_convert_encoding($body, 'UTF-8', 'UTF-8');
        $scrubbed = str_replace("\0", '', $scrubbed);

        return Str::limit($scrubbed, self::MAX_RESPONSE_BODY_LENGTH, '');
    }
}
