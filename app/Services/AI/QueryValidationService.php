<?php

namespace App\Services\AI;

use App\Data\DashboardQueryData;
use App\Exceptions\AI\InvalidQueryException;
use App\Models\Invoice;

class QueryValidationService
{
    private const ALLOWED_ENTITIES = ['invoice'];

    private const ALLOWED_FIELDS = [
        'status',
        'amount',
        'due_date',
        'days_overdue',
        'created_at',
    ];

    private const ALLOWED_OPERATORS = ['=', '>', '<', '>=', '<='];

    private const ALLOWED_STATUSES = [
        Invoice::STATUS_DRAFT,
        Invoice::STATUS_SENT,
        Invoice::STATUS_PARTIAL,
        Invoice::STATUS_PAID,
        Invoice::STATUS_OVERDUE,
    ];

    public function validate(DashboardQueryData $query): void
    {
        // Validate entity
        if (! in_array($query->entity, self::ALLOWED_ENTITIES, true)) {
            throw InvalidQueryException::invalidEntity($query->entity);
        }

        // Validate filters
        foreach ($query->filters as $filter) {
            $this->validateFilter($filter);
        }

        // Validate sort
        if ($query->sort !== null) {
            $this->validateSort($query->sort);
        }

        // Validate limit
        if ($query->limit !== null && $query->limit <= 0) {
            throw InvalidQueryException::invalidValue('limit', $query->limit);
        }
    }

    private function validateFilter(array $filter): void
    {
        if (! isset($filter['field'], $filter['operator'], $filter['value'])) {
            throw InvalidQueryException::invalidValue('filter', json_encode($filter));
        }

        $field = $filter['field'];
        $operator = $filter['operator'];
        $value = $filter['value'];

        if (! in_array($field, self::ALLOWED_FIELDS, true)) {
            throw InvalidQueryException::invalidField($field);
        }

        if (! in_array($operator, self::ALLOWED_OPERATORS, true)) {
            throw InvalidQueryException::invalidOperator($operator);
        }

        if ($field === 'status' && ! in_array($value, self::ALLOWED_STATUSES, true)) {
            throw InvalidQueryException::invalidStatus($value);
        }

        if (in_array($field, ['amount', 'days_overdue'], true)) {
            if (! is_numeric($value)) {
                throw InvalidQueryException::invalidValue($field, $value);
            }
        }
    }

    private function validateSort(array $sort): void
    {
        if (! isset($sort['field'], $sort['direction'])) {
            throw InvalidQueryException::invalidValue('sort', json_encode($sort));
        }

        if (! in_array($sort['field'], self::ALLOWED_FIELDS, true)) {
            throw InvalidQueryException::invalidField($sort['field']);
        }

        $direction = strtolower($sort['direction']);
        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw InvalidQueryException::invalidValue('sort.direction', $sort['direction']);
        }
    }
}
