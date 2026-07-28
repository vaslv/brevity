<?php

namespace App\Filament\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * The dashboard's period selector: a flat list of named presets (today,
 * month-to-date, each of the last twelve calendar months, year-to-date,
 * rolling day-counts) that drives the period-aware stat cards. Each preset
 * resolves to a half-open window `[from, to)` plus the comparable previous
 * window used for the trend line: calendar presets compare
 * calendar-to-calendar (MTD vs the previous month, YTD vs the previous year),
 * in-progress presets are cut at the same elapsed time (today vs yesterday up
 * to the current time), and rolling day-counts compare against the same-length
 * window immediately before.
 */
final class DashboardPeriod
{
    /** The preselected preset: today. */
    public const string DEFAULT = 'TODAY';

    /** Rolling day-count presets, in the order they appear in the select. */
    private const array DAY_COUNTS = [7, 14, 30, 60, 90, 365, 395];

    /** How many trailing calendar months to list as their own presets. */
    private const int TRAILING_MONTHS = 12;

    /**
     * Preset key => label, ordered for the select. Month presets are localised
     * ("Июнь 2026") off the current date, so the list stays current. Day-count
     * keys are numeric strings, which PHP array keys coerce to int.
     *
     * @return array<int|string, string>
     */
    public static function options(): array
    {
        $now = CarbonImmutable::now(date_default_timezone_get());

        $options = [
            'TODAY' => __('dashboard.periods.today'),
            'YESTERDAY' => __('dashboard.periods.yesterday'),
            'DAY_BEFORE_YESTERDAY' => __('dashboard.periods.day_before_yesterday'),
            'MTD' => __('dashboard.periods.mtd'),
        ];

        for ($monthsAgo = 1; $monthsAgo <= self::TRAILING_MONTHS; $monthsAgo++) {
            $options["SUB_MONTHS_$monthsAgo"] = Str::of(
                $now->subMonthsNoOverflow($monthsAgo)->translatedFormat('F Y'),
            )->ucfirst()->value();
        }

        $options['YTD'] = __('dashboard.periods.ytd');
        $options['LAST_YEAR'] = __('dashboard.periods.last_year');

        foreach (self::DAY_COUNTS as $days) {
            $options[(string) $days] = __('dashboard.periods.days', ['days' => $days]);
        }

        return $options;
    }

    /**
     * Resolve the raw page filters to the selected window and its comparable
     * previous window. Falls back to the default preset when the value is
     * missing or unknown.
     *
     * @param  array<string, mixed>  $pageFilters
     * @return array{CarbonImmutable, CarbonImmutable, CarbonImmutable, CarbonImmutable} `[from, to, previousFrom, previousTo]`, half-open
     */
    public static function windows(array $pageFilters): array
    {
        $now = CarbonImmutable::now(date_default_timezone_get());

        $period = $pageFilters['period'] ?? null;
        $presetKey = is_string($period) ? $period : self::DEFAULT;

        [$from, $to] = self::resolve($presetKey, $now);
        [$previousFrom, $previousTo] = self::previous($presetKey, $from, $to);

        return [$from, $to, $previousFrom, $previousTo];
    }

    /**
     * Comparison semantics of the preset: calendar presets shift both bounds
     * by whole months (so an in-progress window is cut at the same elapsed
     * time), day-based presets (null) shift by their day stride.
     */
    private static function calendarShiftMonths(string $period): ?int
    {
        // Including out-of-range SUB_MONTHS_N: resolve() falls back to MTD for
        // them, and MTD carries the same one-month shift.
        if (str_starts_with($period, 'SUB_MONTHS_')) {
            return 1;
        }

        return match ($period) {
            'TODAY', 'YESTERDAY', 'DAY_BEFORE_YESTERDAY' => null,
            'YTD', 'LAST_YEAR' => 12,
            'MTD' => 1,
            // Rolling N-day presets use a day stride; an unknown key falls
            // back to MTD in resolve(), so the shift stays consistent.
            default => in_array($period, array_map('strval', self::DAY_COUNTS), true) ? null : 1,
        };
    }

    /**
     * The previous comparable window for a resolved `[from, to)`. Month-based
     * presets shift both bounds a calendar month/year back — an in-progress
     * "to" (now) lands on the same elapsed time in the previous period; day
     * presets shift by their own length, giving the window immediately before.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private static function previous(string $period, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $shiftMonths = self::calendarShiftMonths($period);

        if ($shiftMonths !== null) {
            return [$from->subMonthsNoOverflow($shiftMonths), $to->subMonthsNoOverflow($shiftMonths)];
        }

        $days = match ($period) {
            'TODAY', 'YESTERDAY', 'DAY_BEFORE_YESTERDAY' => 1,
            default => (int) $period,
        };

        return [$from->subDays($days), $to->subDays($days)];
    }

    /**
     * Resolve a preset key to a half-open window `[from, to)`. In-progress
     * presets (today, month/year-to-date, rolling day-counts) end at "now";
     * elapsed presets (yesterday, a past month, last year) end at their own
     * boundary. Unknown keys fall back to the default preset.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private static function resolve(string $period, CarbonImmutable $now): array
    {
        $today = $now->startOfDay();

        if (str_starts_with($period, 'SUB_MONTHS_')) {
            $monthsAgo = (int) substr($period, strlen('SUB_MONTHS_'));
            if ($monthsAgo >= 1 && $monthsAgo <= self::TRAILING_MONTHS) {
                $monthStart = $today->subMonthsNoOverflow($monthsAgo)->startOfMonth();

                return [$monthStart, $monthStart->addMonthNoOverflow()];
            }
        }

        if (in_array($period, array_map('strval', self::DAY_COUNTS), true)) {
            return [$today->subDays((int) $period - 1), $now];
        }

        return match ($period) {
            'TODAY' => [$today, $now],
            'YESTERDAY' => [$today->subDay(), $today],
            'DAY_BEFORE_YESTERDAY' => [$today->subDays(2), $today->subDay()],
            'MTD' => [$today->startOfMonth(), $now],
            'YTD' => [$today->startOfYear(), $now],
            'LAST_YEAR' => [$today->subYear()->startOfYear(), $today->startOfYear()],
            default => [$today->startOfMonth(), $now],
        };
    }
}
