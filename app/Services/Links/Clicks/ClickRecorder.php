<?php

namespace App\Services\Links\Clicks;

use App\Models\Click;
use App\Models\IpAddress;
use App\Models\Link;
use App\Models\Referrer;
use App\Models\UserAgent;
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

    public function record(Link $link, string $uuid, int $urlId, ?string $ip, ?string $referrer, ?string $userAgent): Click
    {
        $ipValue = $this->normalizeIp($ip);
        $referrerValue = $this->normalizeString($referrer ?? '', self::MAX_REFERRER_LENGTH);
        $userAgentValue = $this->normalizeString($userAgent ?? '', self::MAX_USER_AGENT_LENGTH);

        return DB::transaction(function () use ($link, $uuid, $urlId, $ipValue, $referrerValue, $userAgentValue): Click {
            // Idempotent on `uuid`: a retried RecordClickJob reuses the existing
            // click instead of recording a duplicate visit.
            return Click::query()->firstOrCreate(
                ['uuid' => $uuid],
                [
                    'service_id' => $link->service_id,
                    'link_id' => $link->id,
                    'url_id' => $urlId,
                    'referrer_id' => $this->dictionaryValueResolver->resolveId(Referrer::class, $referrerValue),
                    'user_agent_id' => $this->dictionaryValueResolver->resolveId(UserAgent::class, $userAgentValue),
                    'ip_address_id' => $this->dictionaryValueResolver->resolveId(IpAddress::class, $ipValue),
                ]
            );
        });
    }

    private function normalizeIp(?string $value): ?string
    {
        $value = $this->normalizeString((string) $value, self::MAX_IP_LENGTH);

        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : null;
    }

    private function normalizeString(string $value, int $maxLength): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return Str::limit($value, $maxLength, '');
    }
}
