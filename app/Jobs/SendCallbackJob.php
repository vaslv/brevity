<?php

namespace App\Jobs;

use App\Models\Callback;
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
        Callback::where('id', $this->callbackId)->update(['status' => 'failed']);

        Log::warning('Callback delivery failed permanently.', [
            'callback_id' => $this->callbackId,
            'exception' => $e->getMessage(),
        ]);
    }

    public function handle(): void
    {
        $callback = Callback::with('click.link.service')->findOrFail($this->callbackId);

        $callbackUrl = $callback->click->link->service->callback_url;

        $callback->update([
            'attempts' => $callback->attempts + 1,
            'last_attempt_at' => now(),
        ]);

        $response = Http::timeout(self::HTTP_TIMEOUT_SECONDS)
            ->post($callbackUrl, $callback->data);

        $callback->update([
            'response_code' => $response->status(),
            'response_body' => Str::limit($response->body(), self::MAX_RESPONSE_BODY_LENGTH, ''),
            'status' => $response->successful() ? 'sent' : 'failed',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Callback HTTP {$response->status()} for callback_id={$this->callbackId}"
            );
        }
    }
}
