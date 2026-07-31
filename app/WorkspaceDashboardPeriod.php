<?php

namespace App;

use Carbon\CarbonImmutable;

final readonly class WorkspaceDashboardPeriod
{
    public const THIS_MONTH = 'this_month';

    public const LAST_MONTH = 'last_month';

    public const LAST_3_MONTHS = 'last_3_months';

    public const LAST_6_MONTHS = 'last_6_months';

    public const THIS_YEAR = 'this_year';

    public const TODAY = 'today';

    public const LAST_7_DAYS = 'last_7_days';

    public const CUSTOM = 'custom';

    public const DEFAULT = self::LAST_6_MONTHS;

    public const DASHBOARD_DEFAULT = self::TODAY;

    public const MAX_CUSTOM_RANGE_DAYS = 366;

    public function __construct(
        public string $period,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public string $label,
        public CarbonImmutable $comparisonStart,
        public CarbonImmutable $comparisonEnd,
        public string $comparisonLabel,
        public string $granularity,
    ) {}

    public static function fromInput(
        string $period,
        ?string $startDate = null,
        ?string $endDate = null,
        ?CarbonImmutable $now = null,
    ): self {
        $timezone = (string) config('app.timezone', 'UTC');
        $now ??= CarbonImmutable::now($timezone);
        $monthStart = $now->startOfMonth();

        if (! in_array($period, [
            self::THIS_MONTH,
            self::LAST_MONTH,
            self::LAST_3_MONTHS,
            self::LAST_6_MONTHS,
            self::THIS_YEAR,
            self::TODAY,
            self::LAST_7_DAYS,
            self::CUSTOM,
        ], true)) {
            $period = self::DEFAULT;
        }

        [$start, $end, $label, $comparisonStart, $comparisonEnd, $comparisonLabel] = match ($period) {
            self::THIS_MONTH => [
                $monthStart,
                $now->endOfDay(),
                'This month',
                $monthStart->subMonth(),
                $monthStart->subMonth()->endOfMonth(),
                'Previous calendar month',
            ],
            self::LAST_MONTH => [
                $monthStart->subMonth(),
                $monthStart->subMonth()->endOfMonth(),
                'Last month',
                $monthStart->subMonths(2),
                $monthStart->subMonths(2)->endOfMonth(),
                'Month before last',
            ],
            self::LAST_3_MONTHS => [
                $monthStart->subMonths(2),
                $now->endOfDay(),
                'Last 3 months',
                $monthStart->subMonths(5),
                $monthStart->subMonths(3)->endOfMonth(),
                'Previous 3 calendar months',
            ],
            self::LAST_6_MONTHS => [
                $monthStart->subMonths(5),
                $now->endOfDay(),
                'Last 6 months',
                $monthStart->subMonths(11),
                $monthStart->subMonths(6)->endOfMonth(),
                'Previous 6 calendar months',
            ],
            self::THIS_YEAR => [
                $now->startOfYear(),
                $now->endOfDay(),
                'This year',
                $now->subYear()->startOfYear(),
                $now->subYear()->endOfYear(),
                'Previous calendar year',
            ],
            self::TODAY => [
                $now->startOfDay(),
                $now->endOfDay(),
                'Today',
                $now->subDay()->startOfDay(),
                $now->subDay()->endOfDay(),
                'Yesterday',
            ],
            self::LAST_7_DAYS => [
                $now->subDays(6)->startOfDay(),
                $now->endOfDay(),
                'Last 7 days',
                $now->subDays(13)->startOfDay(),
                $now->subDays(7)->endOfDay(),
                'Previous 7 days',
            ],
            self::CUSTOM => self::customRange($startDate, $endDate, $timezone),
            default => self::fromInput(self::DEFAULT, now: $now)->asTuple(),
        };

        $granularity = in_array($period, [self::TODAY, self::LAST_7_DAYS], true)
            || ($period === self::CUSTOM && $start->startOfDay()->diffInDays($end->startOfDay()) < 31)
            ? 'day'
            : 'month';

        return new self(
            period: $period,
            start: $start,
            end: $end,
            label: $label,
            comparisonStart: $comparisonStart,
            comparisonEnd: $comparisonEnd,
            comparisonLabel: $comparisonLabel,
            granularity: $granularity,
        );
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string, 3: CarbonImmutable, 4: CarbonImmutable, 5: string}
     */
    private static function customRange(?string $startDate, ?string $endDate, string $timezone): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', (string) $startDate, $timezone)->startOfDay();
        $end = CarbonImmutable::createFromFormat('!Y-m-d', (string) $endDate, $timezone)->endOfDay();
        $days = $start->startOfDay()->diffInDays($end->startOfDay());
        $comparisonEnd = $start->subDay()->endOfDay();
        $comparisonStart = $comparisonEnd->subDays($days)->startOfDay();

        return [
            $start,
            $end,
            $start->format('d M Y').' – '.$end->format('d M Y'),
            $comparisonStart,
            $comparisonEnd,
            'Previous range',
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string, 3: CarbonImmutable, 4: CarbonImmutable, 5: string}
     */
    private function asTuple(): array
    {
        return [$this->start, $this->end, $this->label, $this->comparisonStart, $this->comparisonEnd, $this->comparisonLabel];
    }
}
