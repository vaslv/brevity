<?php

namespace Tests\Unit\Conditions;

use App\Models\Condition;
use App\Models\Link;
use App\Services\Links\Conditions\ConditionContext;
use App\Services\Links\Conditions\DeviceConditionHandler;
use App\Services\Links\Conditions\DeviceDetectorDeviceTypeDetector;
use App\Services\Links\Conditions\DeviceTypeDetector;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Stage 3 of docs/07-plans.md — the device condition. A user agent can match
 * several types (an iPhone is both `ios` and `mobile`); a rule keyed on one of
 * them matches. Fails closed on malformed data.
 */
class DeviceConditionHandlerTest extends TestCase
{
    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private const WINDOWS_DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    public function test_detector_returns_empty_types_for_a_blank_ua(): void
    {
        $detector = $this->app->make(DeviceTypeDetector::class);

        $this->assertSame([], $detector->typesFor(null));
        $this->assertSame([], $detector->typesFor('   '));
    }

    public function test_detector_returns_empty_types_for_a_bot(): void
    {
        $detector = $this->app->make(DeviceTypeDetector::class);

        $this->assertSame([], $detector->typesFor(
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
        ));
    }

    /**
     * Pins the OS-name → slug mapping for every declared OS family: a device
     * from that platform matches the corresponding slug. Guards against the
     * library renaming an OS string (which would silently drop the type).
     */
    public function test_each_os_family_maps_to_its_slug(): void
    {
        $cases = [
            'android' => 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
            'macos' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'linux' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:126.0) Gecko/20100101 Firefox/126.0',
            'chromeos' => 'Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        ];

        $detector = $this->app->make(DeviceTypeDetector::class);

        foreach ($cases as $slug => $ua) {
            $this->assertContains($slug, $detector->typesFor($ua), "expected {$slug} for its UA");
        }
    }

    public function test_iphone_matches_both_ios_and_mobile(): void
    {
        $this->assertTrue($this->matchResult('ios', self::IPHONE));
        $this->assertTrue($this->matchResult('mobile', self::IPHONE));
        $this->assertFalse($this->matchResult('desktop', self::IPHONE));
        $this->assertFalse($this->matchResult('android', self::IPHONE));
    }

    public function test_it_fails_closed_on_malformed_data_or_missing_ua(): void
    {
        $this->assertFalse($this->matchResult(null, self::IPHONE));
        $this->assertFalse($this->matchResult('ios', null));
    }

    public function test_the_container_binds_the_device_detector_wrapper(): void
    {
        $this->assertInstanceOf(
            DeviceDetectorDeviceTypeDetector::class,
            $this->app->make(DeviceTypeDetector::class),
        );
    }

    public function test_the_type_slug_is_derived_from_the_class_name(): void
    {
        $this->assertSame('device', DeviceConditionHandler::type());
    }

    public function test_validation_rejects_an_unknown_device_type(): void
    {
        $validator = validator(['device' => 'toaster'], DeviceConditionHandler::rules());
        $this->assertTrue($validator->fails());
    }

    public function test_windows_desktop_matches_windows_and_desktop(): void
    {
        $this->assertTrue($this->matchResult('windows', self::WINDOWS_DESKTOP));
        $this->assertTrue($this->matchResult('desktop', self::WINDOWS_DESKTOP));
        $this->assertFalse($this->matchResult('mobile', self::WINDOWS_DESKTOP));
    }

    private function matchResult(?string $device, ?string $userAgent): bool
    {
        $condition = new Condition(['type' => 'device', 'data' => ['device' => $device]]);
        $context = new ConditionContext(
            new Link,
            Request::create('/', server: $userAgent === null ? [] : ['HTTP_USER_AGENT' => $userAgent]),
            CarbonImmutable::now(),
        );

        return $this->app->make(DeviceConditionHandler::class)->matches($condition, $context);
    }
}
