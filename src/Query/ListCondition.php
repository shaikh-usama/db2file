<?php

declare(strict_types=1);

namespace Db2File\Query;

final readonly class ListCondition
{
    /**
     * @param array<int, mixed> $values
     */
    public function __construct(
        public string $boolean,
        public string $column,
        public array $values,
        public bool $negated = false
    ) {
    }
}