<?php

namespace Tests\Unit\Support;

use App\Filament\Support\DashboardPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Window resolution for the dashboard period selector: each preset maps to a
 * half-open `[from, to)` window plus the comparable previous window. The core
 * invariant under test: in-progress presets (today, MTD, YTD, rolling
 * day-counts) compare against exactly the same period cut at the current
 * elapsed time, elapsed presets compare against the period immediately before.
 */
class DashboardPeriodTest extends TestCase
{
    public function test_a_past_month_compares_against_the_month_before(): void
    {
        $this->assertWindows(
            ['2026-06-01 00:00:00', '2026-07-01 00:00:00', '2026-05-01 00:00:00', '2026-06-01 00:00:00'],
            DashboardPeriod::windows(['period' => 'SUB_MONTHS_1']),
        );
    }

    public function test_a_rolling_day_count_compares_against_the_same_length_window_before(): void
    {
        $this->assertWindows(
            ['2026-06-29 00:00:00', '2026-07-28 15:00:00', '2026-05-30 00:00:00', '2026-06-28 15:00:00'],
            DashboardPeriod::windows(['period' => '30']),
        );
    }

    public function test_last_year_compares_against_the_year_before(): void
    {
        $this->assertWindows(
            ['2025-01-01 00:00:00', '2026-01-01 00:00:00', '2024-01-01 00:00:00', '2025-01-01 00:00:00'],
            DashboardPeriod::windows(['period' => 'LAST_YEAR']),
        );
    }

    public function test_missing_and_unknown_presets_fall_back(): void
    {
        $this->assertSame(
            $this->format(DashboardPeriod::windows(['period' => DashboardPeriod::DEFAULT])),
            $this->format(DashboardPeriod::windows([])),
        );

        // Unknown keys resolve like MTD, month shift included.
        $this->assertSame(
            $this->format(DashboardPeriod::windows(['period' => 'MTD'])),
            $this->format(DashboardPeriod::windows(['period' => 'NOT_A_PRESET'])),
        );
    }

    public function test_month_boundaries_do_not_overflow(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2026-03-31 12:00:00', 'UTC'));

        // February has no 31st: the previous MTD window must clamp to Feb 28.
        $this->assertWindows(
            ['2026-03-01 00:00:00', '2026-03-31 12:00:00', '2026-02-01 00:00:00', '2026-02-28 12:00:00'],
            DashboardPeriod::windows(['period' => 'MTD']),
        );
    }

    public function test_month_to_date_compares_against_the_previous_month_up_to_the_current_time(): void
    {
        $this->assertWindows(
            ['2026-07-01 00:00:00', '2026-07-28 15:00:00', '2026-06-01 00:00:00', '2026-06-28 15:00:00'],
            DashboardPeriod::windows(['period' => 'MTD']),
        );
    }

    public function test_today_compares_against_yesterday_up_to_the_current_time(): void
    {
        $this->assertWindows(
            ['2026-07-28 00:00:00', '2026-07-28 15:00:00', '2026-07-27 00:00:00', '2026-07-27 15:00:00'],
            DashboardPeriod::windows(['period' => 'TODAY']),
        );
    }

    public function test_year_to_date_compares_against_the_previous_year_up_to_the_current_time(): void
    {
        $this->assertWindows(
            ['2026-01-01 00:00:00', '2026-07-28 15:00:00', '2025-01-01 00:00:00', '2025-07-28 15:00:00'],
            DashboardPeriod::windows(['period' => 'YTD']),
        );
    }

    public function test_yesterday_compares_against_the_day_before(): void
    {
        $this->assertWindows(
            ['2026-07-27 00:00:00', '2026-07-28 00:00:00', '2026-07-26 00:00:00', '2026-07-27 00:00:00'],
            DashboardPeriod::windows(['period' => 'YESTERDAY']),
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(new CarbonImmutable('2026-07-28 15:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow(null);

        parent::tearDown();
    }

    /**
     * @param  list<string>  $expected
     * @param  array{CarbonImmutable, CarbonImmutable, CarbonImmutable, CarbonImmutable}  $windows
     */
    private function assertWindows(array $expected, array $windows): void
    {
        $this->assertSame($expected, $this->format($windows));
    }

    /**
     * @param  array{CarbonImmutable, CarbonImmutable, CarbonImmutable, CarbonImmutable}  $windows
     * @return list<string>
     */
    private function format(array $windows): array
    {
        return array_map(
            static fn (CarbonImmutable $bound): string => $bound->format('Y-m-d H:i:s'),
            $windows,
        );
    }
}
