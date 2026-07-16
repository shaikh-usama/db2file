<?php

declare(strict_types=1);

namespace Db2File\Query;

final readonly class Order
{
    public function __construct(
        public string $column,
        public string $direction
    ) {
    }
}