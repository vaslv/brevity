<?php

namespace App\Services\Links\Clicks;

use App\Models\Click;
use App\Models\IpAddress;
use App\Models\Link;
use App\Models\Referrer;
use App\Models\RuleVariant;
use App\Models\UserAgent;
use App\Services\Links\Geo\GeoLocationResolver;
use App\Services\Links\Geo\ResolvedGeoLocation;
use Illuminate\Support\Facades\DB;

readonly class ClickRecorder
{
    private const MAX_IP_BYTES = 45;

    private const MAX_REFERRER_BYTES = 2000;

    private const MAX_USER_AGENT_BYTES = 2000;

    private const MAX_VISITED_QUERY_BYTES = 2000;

    public function __construct(
        private DictionaryValueResolver $dictionaryValueResolver,
        private BotDetector $botDetector,
        private ClickCounterIncrementer $clickCounterIncrementer,
        private GeoLocationResolver $geoLocationResolver,
    ) {}

    public function record(Link $link, string $uuid, int $urlId, ?string $ip, ?string $referrer, ?string $userAgent, ?string $visitedQuery = null, ?int $ruleVariantId = null, ?ResolvedGeoLocation $geo = null, ?string $visitedAt = null): Click
    {
        $ipValue = $this->normalizeIp($ip);
        $referrerValue = $this->normalizeString($referrer ?? '', self::MAX_REFERRER_BYTES);
        $userAgentValue = $this->normalizeString($userAgent ?? '', self::MAX_USER_AGENT_BYTES);
        $visitedQueryValue = $this->normalizeString($visitedQuery ?? '', self::MAX_VISITED_QUERY_BYTES);

        // Dictionary resolution stays OUTSIDE the transaction below: each
        // resolver is race-safe on its own, and keeping them out avoids
        // serializing unrelated inserts (see review 2026-06 — m14).
        $referrerId = $this->dictionaryValueResolver->resolveId(Referrer::class, $referrerValue);
        $userAgentRow = $this->resolveUserAgent($userAgentValue);
        $ipAddressId = $this->dictionaryValueResolver->resolveId(IpAddress::class, $ipValue);
        $geoLocationId = $this->geoLocationResolver->resolveId($geo);

        // A rule rewrite (PATCH) between the redirect and this async job may have
        // deleted the chosen variant. Drop a dangling reference to null rather
        // than hit the FK: the click is a historical fact and must survive. A
        // retry re-checks and converges once the delete has committed.
        $variantId = $ruleVariantId !== null && RuleVariant::whereKey($ruleVariantId)->exists()
            ? $ruleVariantId
            : null;

        // Idempotent on `uuid`: a retried RecordClickJob reuses the existing
        // click instead of recording a duplicate visit. Race-safety comes from
        // the UNIQUE index on clicks.uuid (a concurrent duplicate INSERT violates
        // it and firstOrCreate re-selects the winning row), not from firstOrCreate
        // itself. The counter increment is atomic with the click insert and runs
        // only when the click was actually created — a retry or a lost race
        // never double-counts.
        // Prefer the visit instant captured at redirect time; an older queued
        // payload without it falls back to the insert time (r43).
        $createdAt = $visitedAt !== null ? ['created_at' => $visitedAt] : [];

        return DB::transaction(function () use ($link, $uuid, $urlId, $referrerId, $userAgentRow, $ipAddressId, $variantId, $geoLocationId, $visitedQueryValue, $createdAt): Click {
            $click = Click::query()->firstOrCreate(
                ['uuid' => $uuid],
                [
                    'service_id' => $link->service_id,
                    'link_id' => $link->id,
                    'url_id' => $urlId,
                    'referrer_id' => $referrerId,
                    'user_agent_id' => $userAgentRow?->id,
                    'ip_address_id' => $ipAddressId,
                    'rule_variant_id' => $variantId,
                    'geo_location_id' => $geoLocationId,
                    'visited_query' => $visitedQueryValue,
                    ...$createdAt,
                ]
            );

            if ($click->wasRecentlyCreated) {
                $this->clickCounterIncrementer->increment($link->id, $userAgentRow?->is_bot ?? false);
            }

            return $click;
        });
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

    /**
     * User agents bypass the generic dictionary resolver: detection runs only
     * on the miss path (a racing duplicate may detect too — the insert winner's
     * value persists, detection is deterministic per UA), and existing rows are
     * never re-detected — re-detection after a pattern-library update belongs
     * to the backfill command.
     */
    private function resolveUserAgent(?string $value): ?UserAgent
    {
        if ($value === null) {
            return null;
        }

        $existing = UserAgent::query()->where('value', $value)->first(['id', 'is_bot']);

        if ($existing !== null) {
            return $existing;
        }

        // createOrFirst is race-safe on the UNIQUE `value` index; detection runs
        // only on this miss path, once per unique user agent.
        return UserAgent::query()->createOrFirst(
            ['value' => $value],
            ['is_bot' => $this->botDetector->isBot($value)],
        );
    }
}
