<?php

declare(strict_types=1);

namespace Db2File\Contracts;

use Db2File\Query\CompiledQuery;

interface QueryExecutorInterface
{
    /**
     * @return iterable<array<string, mixed>>
     */
    public function execute(CompiledQuery $query): iterable;
}