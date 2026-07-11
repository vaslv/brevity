<?php

namespace Tests\Feature\Clicks;

use App\Models\UserAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Stage 1 of docs/07-plans.md — the user-agents:detect-bots backfill.
 *
 * The command re-detects the is_bot flag for every dictionary row: it flags
 * crawlers stored before detection existed (post-deploy backfill) and un-flags
 * rows the pattern library no longer considers bots. Concurrent runs are
 * excluded by a cache lock.
 */
class DetectBotUserAgentsCommandTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    private const CRAWLER_UA = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    public function test_it_aborts_when_another_run_holds_the_lock(): void
    {
        $crawler = UserAgent::factory()->create(['value' => self::CRAWLER_UA, 'is_bot' => false]);

        $lock = Cache::lock('user-agents:detect-bots', 60);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('user-agents:detect-bots')->assertFailed();
        } finally {
            $lock->release();
        }

        $this->assertFalse($crawler->refresh()->is_bot);
    }

    public function test_it_flags_crawler_rows_stored_before_detection_existed(): void
    {
        $crawler = UserAgent::factory()->create(['value' => self::CRAWLER_UA, 'is_bot' => false]);
        $browser = UserAgent::factory()->create(['value' => self::BROWSER_UA, 'is_bot' => false]);

        $this->artisan('user-agents:detect-bots')
            ->expectsOutputToContain('1 flag(s) changed')
            ->assertSuccessful();

        $this->assertTrue($crawler->refresh()->is_bot);
        $this->assertFalse($browser->refresh()->is_bot);
    }

    public function test_it_reports_zero_changes_when_flags_are_already_correct(): void
    {
        UserAgent::factory()->bot()->create();
        UserAgent::factory()->create(['value' => self::BROWSER_UA, 'is_bot' => false]);

        $this->artisan('user-agents:detect-bots')
            ->expectsOutputToContain('0 flag(s) changed')
            ->assertSuccessful();
    }

    public function test_it_unflags_rows_the_library_no_longer_considers_bots(): void
    {
        $wronglyFlagged = UserAgent::factory()->create(['value' => self::BROWSER_UA, 'is_bot' => true]);

        $this->artisan('user-agents:detect-bots')->assertSuccessful();

        $this->assertFalse($wronglyFlagged->refresh()->is_bot);
    }
}
