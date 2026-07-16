<?php

declare(strict_types=1);

namespace Db2File\Query;

final readonly class HavingCondition
{
    public function __construct(
        public string $boolean,
        public AggregateFunction $function,
        public string $column,
        public string $operator,
        public mixed $value,
        public bool $distinct = false
    ) {
    }
}