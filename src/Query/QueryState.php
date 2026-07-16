<?php

declare(strict_types=1);

namespace Db2File\Query;

final readonly class QueryState
{
    /**
     * @param array<int, string> $selectedColumns
     * @param array<string, string> $renamedColumns
     * @param array<int, Condition|BetweenCondition|ListCondition|NullCondition> $conditions
     * @param array<int, Aggregate> $aggregates
     * @param array<int, string> $groupBy
     * @param array<int, HavingCondition> $having
     * @param array<int, Order> $orders
     */
    public function __construct(
        public ?string $table = null,
        public array $selectedColumns = [],
        public array $renamedColumns = [],
        public array $conditions = [],
        public array $aggregates = [],
        public array $groupBy = [],
        public array $having = [],
        public array $orders = [],
        public bool $distinct = false,
        public ?int $limit = null,
        public int $offset = 0,
        public ?int $maximumRows = 100000
    ) {
    }
}