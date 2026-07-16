<?php

declare(strict_types=1);

namespace Db2File\Query;

final readonly class BetweenCondition
{
    public function __construct(
        public string $boolean,
        public string $column,
        public mixed $from,
        public mixed $to,
        public bool $negated = false
    ) {
    }
}