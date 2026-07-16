<?php

declare(strict_types=1);

namespace Db2File\Database;

use Db2File\Contracts\QueryExecutorInterface;
use Db2File\Exceptions\QueryExecutionException;
use Db2File\Query\CompiledQuery;
use Generator;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

final class MySqlExecutor implements QueryExecutorInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function execute(CompiledQuery $query): Generator
    {
        try {
            $statement = $this->pdo->prepare($query->sql);

            if (!$statement instanceof PDOStatement) {
                throw new QueryExecutionException(
                    'Unable to prepare the export query.'
                );
            }

            foreach ($query->bindings as $parameter => $value) {
                $statement->bindValue(
                    ':' . ltrim($parameter, ':'),
                    $value,
                    $this->detectPdoType($value)
                );
            }

            $statement->execute();

            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                yield $row;
            }
        } catch (QueryExecutionException $exception) {
            throw $exception;
        } catch (PDOException $exception) {
            throw new QueryExecutionException(
                'The database export query failed: '
                . $exception->getMessage(),
                previous: $exception
            );
        } catch (Throwable $exception) {
            throw new QueryExecutionException(
                'An unexpected error occurred while executing the export query.',
                previous: $exception
            );
        }
    }

    private function detectPdoType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }
}