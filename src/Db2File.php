<?php

declare(strict_types=1);

namespace Db2File;

use Db2File\Query\IdentifierValidator;
use Db2File\Query\QueryBuilder;
use mysqli;
use PDO;

final class Db2File
{
    private function __construct()
    {
    }

    public static function make(PDO|mysqli $connection): QueryBuilder
    {
        if ($connection instanceof PDO) {
            $connection->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            $connection->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );
        }

        return new QueryBuilder(
            $connection,
            new IdentifierValidator()
        );
    }
}
