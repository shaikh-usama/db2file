<?php

declare(strict_types=1);

namespace Db2File\Query;

final readonly class Condition
{
    public function __construct(
        public string $boolean,
        public string $column,
        public string $operator,
        public mixed $value = null
    ) {
    }
}