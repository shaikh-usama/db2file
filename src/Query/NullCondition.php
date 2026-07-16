<?php

declare(strict_types=1);

namespace Db2File\Query;

final readonly class NullCondition
{
    public function __construct(
        public string $boolean,
        public string $column,
        public bool $negated = false
    ) {
    }
}