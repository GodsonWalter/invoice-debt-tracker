<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
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
            'range' => ['nullable', 'string', Rule::in([
                'this_month',
                'last_month',
                'last_3_months',
                'last_6_months',
                'this_year',
                'custom',
            ])],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('range')) {
            $this->merge(['range' => 'last_6_months']);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->string('range')->toString() !== 'custom') {
                return;
            }

            if (! $this->filled('start_date') || ! $this->filled('end_date')) {
                $validator->errors()->add('start_date', 'A start and end date are required for a custom range.');

                return;
            }

            $start = CarbonImmutable::createFromFormat('Y-m-d', $this->string('start_date')->toString());
            $end = CarbonImmutable::createFromFormat('Y-m-d', $this->string('end_date')->toString());

            if ($start && $end && $start->diffInDays($end) > 366) {
                $validator->errors()->add('end_date', 'Dashboard date ranges cannot exceed 366 days.');
            }
        });
    }

    /**
     * @return array{range: string, start: CarbonImmutable, end: CarbonImmutable, label: string}
     */
    public function dashboardFilters(): array
    {
        $range = $this->string('range')->toString() ?: 'last_6_months';
        $now = CarbonImmutable::now();

        [$start, $end, $label] = match ($range) {
            'this_month' => [$now->startOfMonth(), $now->endOfMonth(), 'This month'],
            'last_month' => [$now->subMonth()->startOfMonth(), $now->subMonth()->endOfMonth(), 'Last month'],
            'last_3_months' => [$now->subMonths(2)->startOfMonth(), $now->endOfMonth(), 'Last 3 months'],
            'this_year' => [$now->startOfYear(), $now->endOfYear(), 'This year'],
            'custom' => [
                CarbonImmutable::createFromFormat('Y-m-d', $this->string('start_date')->toString())->startOfDay(),
                CarbonImmutable::createFromFormat('Y-m-d', $this->string('end_date')->toString())->endOfDay(),
                $this->string('start_date')->toString().' to '.$this->string('end_date')->toString(),
            ],
            default => [$now->subMonths(5)->startOfMonth(), $now->endOfMonth(), 'Last 6 months'],
        };

        return compact('range', 'start', 'end', 'label');
    }
}
