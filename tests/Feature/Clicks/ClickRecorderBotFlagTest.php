<?php

namespace Tests\Feature\Clicks;

use App\Models\Click;
use App\Models\Link;
use App\Models\Url;
use App\Models\UserAgent;
use App\Services\Links\Clicks\BotDetector;
use App\Services\Links\Clicks\ClickRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Stage 1 of docs/07-plans.md — the bot flag lives on the user_agents
 * dictionary, not on every click.
 *
 * A brand-new dictionary row is flagged exactly once at creation; an existing
 * row is never re-detected during click recording (re-detection belongs to the
 * backfill command). A click without a user agent has no flag to compute.
 */
class ClickRecorderBotFlagTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    private const CRAWLER_UA = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    public function test_a_blank_user_agent_has_no_dictionary_row(): void
    {
        $click = $this->recordClick(userAgent: '   ');

        $this->assertNull($click->user_agent_id);
        $this->assertDatabaseCount('user_agents', 0);
    }

    public function test_a_click_without_user_agent_has_no_dictionary_row(): void
    {
        $click = $this->recordClick(userAgent: null);

        $this->assertNull($click->user_agent_id);
        $this->assertDatabaseCount('user_agents', 0);
    }

    public function test_a_new_browser_user_agent_row_is_not_flagged(): void
    {
        $click = $this->recordClick(userAgent: self::BROWSER_UA);

        $this->assertDatabaseHas(UserAgent::class, [
            'id' => $click->user_agent_id,
            'is_bot' => false,
        ]);
    }

    public function test_a_new_crawler_user_agent_row_is_flagged_as_bot(): void
    {
        $click = $this->recordClick(userAgent: self::CRAWLER_UA);

        $this->assertNotNull($click->user_agent_id);
        $this->assertDatabaseHas(UserAgent::class, [
            'id' => $click->user_agent_id,
            'value' => self::CRAWLER_UA,
            'is_bot' => true,
        ]);
    }

    public function test_an_existing_user_agent_row_is_reused_and_never_re_detected(): void
    {
        // A crawler UA stored before detection existed (backfill not yet run):
        // recording another click must reuse the row as-is, not re-flag it.
        $existing = UserAgent::factory()->create([
            'value' => self::CRAWLER_UA,
            'is_bot' => false,
        ]);

        $click = $this->recordClick(userAgent: self::CRAWLER_UA);

        $this->assertSame($existing->id, $click->user_agent_id);
        $this->assertDatabaseCount('user_agents', 1);
        $this->assertFalse($existing->refresh()->is_bot);
    }

    public function test_detection_runs_only_when_the_dictionary_row_is_created(): void
    {
        $detector = new class implements BotDetector
        {
            public int $calls = 0;

            public function isBot(?string $userAgent): bool
            {
                $this->calls++;

                return false;
            }
        };
        $this->app->instance(BotDetector::class, $detector);

        $this->recordClick(userAgent: self::BROWSER_UA);
        $this->recordClick(userAgent: self::BROWSER_UA);

        // The second click hits the SELECT-first hot path: the row already
        // exists, so the detector must not run again.
        $this->assertSame(1, $detector->calls);
    }

    private function recordClick(?string $userAgent): Click
    {
        $link = Link::factory()->create();
        $url = Url::factory()->create();

        return app(ClickRecorder::class)->record(
            $link,
            (string) Str::uuid(),
            $url->id,
            '203.0.113.10',
            null,
            $userAgent,
        );
    }
}
