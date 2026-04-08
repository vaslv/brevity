<?php

namespace App\Services\Links\Clicks;

use App\Models\Click;
use App\Models\IpAddress;
use App\Models\Link;
use App\Models\Referrer;
use App\Models\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

readonly class ClickRecorder
{
    private const MAX_IP_LENGTH = 45;

    private const MAX_REFERRER_LENGTH = 2048;

    private const MAX_USER_AGENT_LENGTH = 1024;

    public function __construct(
        private DictionaryValueResolver $dictionaryValueResolver
    ) {}

    public function record(Request $request, Link $link, int $urlId): void
    {
        $referrerValue = $this->normalizeReferrer($request);
        $userAgentValue = $this->normalizeUserAgent($request);
        $ipAddressValue = $this->normalizeIpAddress($request);

        DB::transaction(function () use ($link, $urlId, $referrerValue, $userAgentValue, $ipAddressValue): void {
            Click::query()->create([
                'service_id' => $link->service_id,
                'link_id' => $link->id,
                'url_id' => $urlId,
                'referrer_id' => $this->dictionaryValueResolver->resolveId(Referrer::class, $referrerValue),
                'user_agent_id' => $this->dictionaryValueResolver->resolveId(UserAgent::class, $userAgentValue),
                'ip_address_id' => $this->dictionaryValueResolver->resolveId(IpAddress::class, $ipAddressValue),
            ]);
        });
    }

    private function normalizeIpAddress(Request $request): ?string
    {
        $value = $this->normalizeString((string) $request->ip(), self::MAX_IP_LENGTH);

        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_IP) !== false
            ? $value
            : null;
    }

    private function normalizeReferrer(Request $request): ?string
    {
        return $this->normalizeString(
            (string) $request->headers->get('referer', ''),
            self::MAX_REFERRER_LENGTH
        );
    }

    private function normalizeString(string $value, int $maxLength): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return Str::limit($value, $maxLength, '');
    }

    private function normalizeUserAgent(Request $request): ?string
    {
        return $this->normalizeString((string) $request->userAgent(), self::MAX_USER_AGENT_LENGTH);
    }
}
