<?php

namespace Tests\Unit\Clicks;

use App\Services\Links\Clicks\BotDetector;
use App\Services\Links\Clicks\DeviceDetectorBotDetector;
use Tests\TestCase;

/**
 * Stage 1 of docs/07-plans.md — bot detection behind our own interface.
 *
 * The concrete detector wraps matomo/device-detector (migrated from
 * crawler-detect in stage 3); the interface is the single seam for swapping
 * the library. A missing or blank user agent is never a bot.
 */
class BotDetectorTest extends TestCase
{
    public function test_a_known_crawler_user_agent_is_a_bot(): void
    {
        $this->assertTrue($this->detector()->isBot(
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
        ));
    }

    public function test_a_less_common_crawler_is_detected(): void
    {
        // device-detector's bot list is broader than crawler-detect's — this
        // monitoring bot exercises the wider coverage after the migration.
        $this->assertTrue($this->detector()->isBot(
            'Mozilla/5.0 (compatible; UptimeRobot/2.0; http://www.uptimerobot.com/)'
        ));
    }

    public function test_a_null_or_blank_user_agent_is_not_a_bot(): void
    {
        $this->assertFalse($this->detector()->isBot(null));
        $this->assertFalse($this->detector()->isBot(''));
        $this->assertFalse($this->detector()->isBot('   '));
    }

    public function test_a_regular_browser_user_agent_is_not_a_bot(): void
    {
        $this->assertFalse($this->detector()->isBot(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36'
        ));
    }

    public function test_container_resolves_the_interface_to_the_device_detector_wrapper(): void
    {
        $this->assertInstanceOf(
            DeviceDetectorBotDetector::class,
            $this->app->make(BotDetector::class),
        );
    }

    private function detector(): BotDetector
    {
        return $this->app->make(BotDetector::class);
    }
}
