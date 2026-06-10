<?php

namespace App\Data;

readonly class DashboardQueryData
{
    public function __construct(
        public string $entity,
        public array $filters,
        public ?array $sort = null,
        public ?int $limit = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            entity: $data['entity'] ?? '',
            filters: $data['filters'] ?? [],
            sort: $data['sort'] ?? null,
            limit: $data['limit'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'entity' => $this->entity,
            'filters' => $this->filters,
            'sort' => $this->sort,
            'limit' => $this->limit,
        ];
    }
}
