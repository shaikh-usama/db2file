<?php

declare(strict_types=1);

namespace Db2File\Query;

final readonly class Aggregate
{
    public function __construct(
        public AggregateFunction $function,
        public string $column,
        public string $alias,
        public string $heading,
        public bool $distinct = false
    ) {
    }
}