<?php

namespace App\Http\Requests;

use App\Data\ReportFilters;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Workspace;
use App\ReportType;
use App\WorkspaceDashboardPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->currentWorkspace();

        return auth()->check()
            && $workspace instanceof Workspace
            && $workspace->canBeManagedBy($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $workspace = $this->currentWorkspace();

        return [
            'period' => ['nullable', 'string', Rule::in($this->supportedPeriods())],
            'range' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:today'],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists((new Client)->getTable(), 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspace?->id ?? 0),
                ),
            ],
            'invoice_status' => ['nullable', 'string', Rule::in(Invoice::STATUSES)],
            'reminder_status' => ['nullable', 'string', Rule::in(['pending', 'sent', 'failed'])],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'amount_min' => ['nullable', 'numeric', 'min:0'],
            'amount_max' => ['nullable', 'numeric', 'gte:amount_min'],
            'sort' => ['nullable', 'string', Rule::in(['date', 'amount', 'customer', 'days_overdue', 'outstanding'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('period')) {
            $this->merge([
                'period' => $this->filled('range') ? $this->input('range') : WorkspaceDashboardPeriod::DEFAULT,
            ]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->string('period')->toString() !== WorkspaceDashboardPeriod::CUSTOM) {
                return;
            }

            if (! $this->filled('start_date') || ! $this->filled('end_date')) {
                $validator->errors()->add('start_date', 'A start and end date are required for a custom range.');

                return;
            }

            $start = date_create_immutable($this->string('start_date')->toString());
            $end = date_create_immutable($this->string('end_date')->toString());

            if ($start && $end && $start->diff($end)->days + 1 > WorkspaceDashboardPeriod::MAX_CUSTOM_RANGE_DAYS) {
                $validator->errors()->add('end_date', 'Report date ranges cannot exceed 366 days.');
            }
        });
    }

    public function filters(): ReportFilters
    {
        $type = ReportType::tryFrom((string) ($this->route('report') ?: $this->input('report', ReportType::REVENUE->value)));

        abort_if($type === null, 404);

        return new ReportFilters(
            type: $type,
            period: WorkspaceDashboardPeriod::fromInput(
                period: $this->string('period')->toString() ?: WorkspaceDashboardPeriod::DEFAULT,
                startDate: $this->string('start_date')->toString() ?: null,
                endDate: $this->string('end_date')->toString() ?: null,
            ),
            customerId: $this->integer('customer_id') ?: null,
            invoiceStatus: $this->string('invoice_status')->toString() ?: null,
            reminderStatus: $this->string('reminder_status')->toString() ?: null,
            paymentMethod: $this->string('payment_method')->toString() ?: null,
            search: $this->string('search')->trim()->toString() ?: null,
            amountMin: $this->filled('amount_min') ? (float) $this->input('amount_min') : null,
            amountMax: $this->filled('amount_max') ? (float) $this->input('amount_max') : null,
            sort: $this->string('sort')->toString() ?: 'date',
            direction: $this->string('direction')->toString() ?: 'desc',
        );
    }

    private function currentWorkspace(): ?Workspace
    {
        return app()->bound('currentWorkspace') ? app('currentWorkspace') : null;
    }

    /**
     * @return array<int, string>
     */
    private function supportedPeriods(): array
    {
        return [
            WorkspaceDashboardPeriod::THIS_MONTH,
            WorkspaceDashboardPeriod::LAST_MONTH,
            WorkspaceDashboardPeriod::LAST_3_MONTHS,
            WorkspaceDashboardPeriod::LAST_6_MONTHS,
            WorkspaceDashboardPeriod::THIS_YEAR,
            WorkspaceDashboardPeriod::CUSTOM,
        ];
    }
}
