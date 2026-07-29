<?php

use App\WorkspaceDashboardPeriod;
use Carbon\CarbonImmutable;

function dashboardPeriodNow(): CarbonImmutable
{
    return CarbonImmutable::create(2026, 7, 29, 15, 30, 0, 'UTC');
}

test('dashboard periods use consistent inclusive boundaries and comparisons', function (string $period, string $start, string $end, string $comparisonStart, string $comparisonEnd, string $comparisonLabel, string $granularity): void {
    $dashboardPeriod = WorkspaceDashboardPeriod::fromInput($period, now: dashboardPeriodNow());

    expect($dashboardPeriod->start->toDateTimeString())->toBe($start)
        ->and($dashboardPeriod->end->toDateTimeString())->toBe($end)
        ->and($dashboardPeriod->comparisonStart->toDateTimeString())->toBe($comparisonStart)
        ->and($dashboardPeriod->comparisonEnd->toDateTimeString())->toBe($comparisonEnd)
        ->and($dashboardPeriod->comparisonLabel)->toBe($comparisonLabel)
        ->and($dashboardPeriod->granularity)->toBe($granularity);
})->with([
    'this month' => [
        WorkspaceDashboardPeriod::THIS_MONTH,
        '2026-07-01 00:00:00',
        '2026-07-29 23:59:59',
        '2026-06-01 00:00:00',
        '2026-06-30 23:59:59',
        'Previous calendar month',
        'month',
    ],
    'last month' => [
        WorkspaceDashboardPeriod::LAST_MONTH,
        '2026-06-01 00:00:00',
        '2026-06-30 23:59:59',
        '2026-05-01 00:00:00',
        '2026-05-31 23:59:59',
        'Month before last',
        'month',
    ],
    'last three months' => [
        WorkspaceDashboardPeriod::LAST_3_MONTHS,
        '2026-05-01 00:00:00',
        '2026-07-29 23:59:59',
        '2026-02-01 00:00:00',
        '2026-04-30 23:59:59',
        'Previous 3 calendar months',
        'month',
    ],
    'last six months' => [
        WorkspaceDashboardPeriod::LAST_6_MONTHS,
        '2026-02-01 00:00:00',
        '2026-07-29 23:59:59',
        '2025-08-01 00:00:00',
        '2026-01-31 23:59:59',
        'Previous 6 calendar months',
        'month',
    ],
    'this year' => [
        WorkspaceDashboardPeriod::THIS_YEAR,
        '2026-01-01 00:00:00',
        '2026-07-29 23:59:59',
        '2025-01-01 00:00:00',
        '2025-12-31 23:59:59',
        'Previous calendar year',
        'month',
    ],
]);

test('custom dashboard periods are inclusive and use daily chart buckets for short ranges', function (): void {
    $dashboardPeriod = WorkspaceDashboardPeriod::fromInput(
        WorkspaceDashboardPeriod::CUSTOM,
        '2026-07-01',
        '2026-07-03',
        dashboardPeriodNow(),
    );

    expect($dashboardPeriod->start->toDateTimeString())->toBe('2026-07-01 00:00:00')
        ->and($dashboardPeriod->end->toDateTimeString())->toBe('2026-07-03 23:59:59')
        ->and($dashboardPeriod->comparisonStart->toDateTimeString())->toBe('2026-06-28 00:00:00')
        ->and($dashboardPeriod->comparisonEnd->toDateTimeString())->toBe('2026-06-30 23:59:59')
        ->and($dashboardPeriod->granularity)->toBe('day');
});

test('a one-day custom period remains inclusive and uses a single daily bucket', function (): void {
    $dashboardPeriod = WorkspaceDashboardPeriod::fromInput(
        WorkspaceDashboardPeriod::CUSTOM,
        '2026-07-29',
        '2026-07-29',
        dashboardPeriodNow(),
    );

    expect($dashboardPeriod->start->toDateTimeString())->toBe('2026-07-29 00:00:00')
        ->and($dashboardPeriod->end->toDateTimeString())->toBe('2026-07-29 23:59:59')
        ->and($dashboardPeriod->comparisonStart->toDateString())->toBe('2026-07-28')
        ->and($dashboardPeriod->comparisonEnd->toDateString())->toBe('2026-07-28')
        ->and($dashboardPeriod->granularity)->toBe('day');
});

test('custom periods longer than one month use monthly chart buckets', function (): void {
    $dashboardPeriod = WorkspaceDashboardPeriod::fromInput(
        WorkspaceDashboardPeriod::CUSTOM,
        '2026-01-01',
        '2026-02-01',
        dashboardPeriodNow(),
    );

    expect($dashboardPeriod->granularity)->toBe('month');
});

test('unsupported periods safely resolve to the default period', function (): void {
    $dashboardPeriod = WorkspaceDashboardPeriod::fromInput('unsupported', now: dashboardPeriodNow());

    expect($dashboardPeriod->period)->toBe(WorkspaceDashboardPeriod::DEFAULT)
        ->and($dashboardPeriod->start->toDateString())->toBe('2026-02-01');
});

test('custom period date parsing handles leap days and year transitions', function (): void {
    $dashboardPeriod = WorkspaceDashboardPeriod::fromInput(
        WorkspaceDashboardPeriod::CUSTOM,
        '2024-02-29',
        '2024-03-01',
        CarbonImmutable::create(2024, 3, 15, 10, 0, 0, 'UTC'),
    );

    expect($dashboardPeriod->start->toDateString())->toBe('2024-02-29')
        ->and($dashboardPeriod->end->toDateString())->toBe('2024-03-01')
        ->and($dashboardPeriod->comparisonStart->toDateString())->toBe('2024-02-27')
        ->and($dashboardPeriod->comparisonEnd->toDateString())->toBe('2024-02-28');
});
