<?php

namespace App\Services\Links\Clicks;

use App\Models\Click;
use App\Models\IpAddress;
use App\Models\Link;
use App\Models\Referrer;
use App\Models\UserAgent;

readonly class ClickRecorder
{
    private const MAX_IP_BYTES = 45;

    private const MAX_REFERRER_BYTES = 2000;

    private const MAX_USER_AGENT_BYTES = 2000;

    public function __construct(
        private DictionaryValueResolver $dictionaryValueResolver
    ) {}

    public function record(Link $link, string $uuid, int $urlId, ?string $ip, ?string $referrer, ?string $userAgent): Click
    {
        $ipValue = $this->normalizeIp($ip);
        $referrerValue = $this->normalizeString($referrer ?? '', self::MAX_REFERRER_BYTES);
        $userAgentValue = $this->normalizeString($userAgent ?? '', self::MAX_USER_AGENT_BYTES);

        // Idempotent on `uuid`: a retried RecordClickJob reuses the existing
        // click instead of recording a duplicate visit. No wrapping transaction
        // is needed — firstOrCreate is race-safe on its own (savepoint + retry),
        // as are the insertOrIgnore-based dictionary resolvers.
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
    }

    private function normalizeIp(?string $value): ?string
    {
        $value = $this->normalizeString((string) $value, self::MAX_IP_BYTES);

        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : null;
    }

    private function normalizeString(string $value, int $maxBytes): ?string
    {
        // Scrub invalid UTF-8 sequences and NUL bytes: Postgres text columns
        // reject both, and mb_strcut needs valid UTF-8 to cut on a character
        // boundary.
        $value = str_replace("\0", '', mb_convert_encoding($value, 'UTF-8', 'UTF-8'));
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // Cap by BYTES, not characters. The referrers/user_agents `value` columns
        // carry a UNIQUE btree index whose key is limited to ~2704 bytes. A long
        // multibyte Referer/User-Agent header (attacker-controlled) of N chars is
        // up to 4N bytes; capping by characters let it overflow the index, which
        // threw SQLSTATE 54000 and silently dropped the click.
        return mb_strcut($value, 0, $maxBytes, 'UTF-8');
    }
}
