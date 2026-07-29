<?php

namespace App\Http\Requests;

use App\WorkspaceDashboardPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkspaceDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string', Rule::in($this->supportedPeriods())],
            'range' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:today'],
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
                $validator->errors()->add('end_date', 'Dashboard date ranges cannot exceed 366 days.');
            }
        });
    }

    public function dashboardPeriod(): WorkspaceDashboardPeriod
    {
        return WorkspaceDashboardPeriod::fromInput(
            period: $this->string('period')->toString() ?: WorkspaceDashboardPeriod::DEFAULT,
            startDate: $this->string('start_date')->toString() ?: null,
            endDate: $this->string('end_date')->toString() ?: null,
        );
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
