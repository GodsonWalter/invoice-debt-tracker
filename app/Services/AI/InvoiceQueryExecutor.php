<?php

namespace App\Services\AI;

use App\Data\DashboardQueryData;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InvoiceQueryExecutor
{
    public function execute(DashboardQueryData $query, int $workspaceId): Collection
    {
        $builder = Invoice::query()
            ->with(['client', 'currency'])
            ->where('workspace_id', $workspaceId);

        // Apply filters
        foreach ($query->filters as $filter) {
            $builder = $this->applyFilter($builder, $filter);
        }

        // Apply sort
        if ($query->sort !== null) {
            $direction = strtolower($query->sort['direction'] ?? 'asc');
            $mappedField = $this->mapFieldToColumn($query->sort['field']);
            $builder = $builder->orderBy($mappedField, $direction);
        }

        // Apply limit
        if ($query->limit !== null) {
            $builder = $builder->limit($query->limit);
        }

        return $builder->get();
    }

    private function applyFilter(Builder $builder, array $filter): Builder
    {
        $field = $filter['field'];
        $operator = $filter['operator'];
        $value = $filter['value'];

        switch ($field) {
            case 'status':
                return $builder->where('status', $operator, $value);

            case 'amount':
                return $builder->where('total_amount', $operator, $value);

            case 'due_date':
                return $builder->where('due_date', $operator, $value);

            case 'days_overdue':
                return $this->filterByDaysOverdue($builder, $operator, $value);

            case 'created_at':
                return $builder->where('created_at', $operator, Carbon::parse($value)->startOfDay());

            default:
                return $builder;
        }
    }

    private function filterByDaysOverdue(Builder $builder, string $operator, int $days): Builder
    {
        $cutoffDate = Carbon::now()->subDays($days);

        // An invoice is overdue if its due_date is before the cutoff date
        // and it's either overdue status or has an amount > 0 and amount_paid < total_amount
        switch ($operator) {
            case '>':
                // More overdue than X days
                return $builder
                    ->where('due_date', '<', $cutoffDate)
                    ->where('status', Invoice::STATUS_OVERDUE);

            case '>=':
                // Overdue for X days or more
                return $builder
                    ->where('due_date', '<=', $cutoffDate)
                    ->where('status', Invoice::STATUS_OVERDUE);

            case '<':
                // Less overdue than X days
                return $builder
                    ->where('due_date', '>', $cutoffDate)
                    ->where('status', Invoice::STATUS_OVERDUE);

            case '<=':
                // Overdue for X days or less
                return $builder
                    ->where('due_date', '>=', $cutoffDate)
                    ->where('status', Invoice::STATUS_OVERDUE);

            case '=':
            default:
                return $builder;
        }
    }

    private function mapFieldToColumn(string $field): string
    {
        return match ($field) {
            'amount' => 'total_amount',
            default => $field,
        };
    }
}
