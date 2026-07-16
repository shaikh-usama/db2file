<?php

declare(strict_types=1);

namespace Db2File\Query;

final readonly class CompiledQuery
{
    /**
     * @param array<string, mixed> $bindings
     */
    public function __construct(
        public string $sql,
        public array $bindings = []
    ) {
    }
}