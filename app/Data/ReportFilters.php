<?php

namespace App\Data;

use App\ReportType;
use App\WorkspaceDashboardPeriod;

final readonly class ReportFilters
{
    public function __construct(
        public ReportType $type,
        public WorkspaceDashboardPeriod $period,
        public ?int $customerId = null,
        public ?string $invoiceStatus = null,
        public ?string $reminderStatus = null,
        public ?string $paymentMethod = null,
        public ?string $search = null,
        public ?float $amountMin = null,
        public ?float $amountMax = null,
        public string $sort = 'date',
        public string $direction = 'desc',
    ) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public static function fromQuery(ReportType $type, array $query): self
    {
        return new self(
            type: $type,
            period: WorkspaceDashboardPeriod::fromInput(
                period: (string) ($query['period'] ?? WorkspaceDashboardPeriod::DEFAULT),
                startDate: isset($query['start_date']) ? (string) $query['start_date'] : null,
                endDate: isset($query['end_date']) ? (string) $query['end_date'] : null,
            ),
            customerId: isset($query['customer_id']) ? (int) $query['customer_id'] : null,
            invoiceStatus: isset($query['invoice_status']) ? (string) $query['invoice_status'] : null,
            reminderStatus: isset($query['reminder_status']) ? (string) $query['reminder_status'] : null,
            paymentMethod: isset($query['payment_method']) ? (string) $query['payment_method'] : null,
            search: isset($query['search']) ? (string) $query['search'] : null,
            amountMin: isset($query['amount_min']) ? (float) $query['amount_min'] : null,
            amountMax: isset($query['amount_max']) ? (float) $query['amount_max'] : null,
            sort: (string) ($query['sort'] ?? 'date'),
            direction: (string) ($query['direction'] ?? 'desc'),
        );
    }

    /**
     * @return array<string, string|int|float|null>
     */
    public function toQuery(): array
    {
        return array_filter([
            'report' => $this->type->value,
            'period' => $this->period->period,
            'start_date' => $this->period->period === WorkspaceDashboardPeriod::CUSTOM ? $this->period->start->toDateString() : null,
            'end_date' => $this->period->period === WorkspaceDashboardPeriod::CUSTOM ? $this->period->end->toDateString() : null,
            'customer_id' => $this->customerId,
            'invoice_status' => $this->invoiceStatus,
            'reminder_status' => $this->reminderStatus,
            'payment_method' => $this->paymentMethod,
            'search' => $this->search,
            'amount_min' => $this->amountMin,
            'amount_max' => $this->amountMax,
            'sort' => $this->sort,
            'direction' => $this->direction,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
