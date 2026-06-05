<?php

namespace Tests\Feature\Regressions;

use App\Models\Link;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression for docs/AUDIT_2026-06.md — C1 (Critical) and M1.
 *
 * referrers.value / user_agents.value / urls.value carry a UNIQUE btree index
 * whose key is limited to ~2704 bytes. ClickRecorder capped dictionary values by
 * CHARACTER count, so a long multibyte Referer/User-Agent header (attacker-
 * controlled, up to 4 bytes per char) overflowed the index with SQLSTATE 54000
 * inside DictionaryValueResolver — failing RecordClickJob and silently dropping
 * the click.
 *
 * Fix: cap referrer/user-agent by BYTES (mb_strcut) so click recording can never
 * overflow the index; reject (422) an over-long destination URL at the API
 * instead of overflowing on insert (a redirect target must not be truncated).
 */
class DictionaryValueByteOverflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_long_multibyte_referrer_and_user_agent_still_record_the_click(): void
    {
        $code = $this->createLink('https://example.com/landing');

        // 2048 incompressible CJK characters ≈ 6144 bytes — well over the
        // 2704-byte btree limit (incompressible so TOAST cannot shrink it).
        $hugeReferrer = $this->incompressibleMultibyte(2048, seed: 1);
        $hugeUserAgent = $this->incompressibleMultibyte(2048, seed: 7);

        // QUEUE_CONNECTION=sync under phpunit.xml: the click is recorded inline,
        // so a btree overflow would surface as a 500 on this request.
        $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.7',
            'HTTP_REFERER' => $hugeReferrer,
            'HTTP_USER_AGENT' => $hugeUserAgent,
        ])->get('/'.$code)->assertRedirect();

        $this->assertDatabaseCount('clicks', 1);

        $referrer = (string) DB::table('referrers')->value('value');
        $userAgent = (string) DB::table('user_agents')->value('value');

        $this->assertNotSame('', $referrer);
        $this->assertNotSame('', $userAgent);
        $this->assertLessThanOrEqual(2000, strlen($referrer));
        $this->assertLessThanOrEqual(2000, strlen($userAgent));
    }

    public function test_over_long_destination_url_is_rejected_with_422(): void
    {
        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);
        $token = $service->createToken('test')->plainTextToken;

        $longUrl = 'https://example.com/'.str_repeat('a', 2100); // > 2000 bytes

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/links', ['rules' => [['url' => $longUrl]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rules.0.url']);

        $this->assertDatabaseCount('links', 0);
    }

    private function createLink(string $targetUrl): string
    {
        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);

        $link = Link::query()->create([
            'service_id' => $service->id,
            'forward_query' => false,
        ]);

        $code = fake()->unique()->bothify('????####');
        $link->update(['code' => $code]);

        $url = Url::query()->create(['value' => $targetUrl]);

        Rule::query()->create([
            'link_id' => $link->id,
            'url_id' => $url->id,
            'priority' => 1,
        ]);

        return $code;
    }

    private function incompressibleMultibyte(int $chars, int $seed): string
    {
        $value = '';

        for ($i = 0; $i < $chars; $i++) {
            // Spread codepoints across the CJK block (3 bytes each) so the value
            // is incompressible and genuinely exceeds the btree key limit.
            $value .= mb_chr(0x4E00 + (($i * 2654435761 + $seed) % 20000), 'UTF-8');
        }

        return $value;
    }
}
