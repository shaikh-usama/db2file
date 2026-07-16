<?php

declare(strict_types=1);

namespace Db2File\Database;

use Db2File\Contracts\QueryExecutorInterface;
use Db2File\Exceptions\QueryExecutionException;
use Db2File\Query\CompiledQuery;
use Generator;
use mysqli;
use mysqli_sql_exception;
use mysqli_stmt;
use Throwable;

final class MySqliExecutor implements QueryExecutorInterface
{
    public function __construct(
        private readonly mysqli $connection
    ) {
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function execute(CompiledQuery $query): Generator
    {
        $statement = null;

        try {
            [$sql, $values] = $this->prepareBindings($query);
            $statement = $this->connection->prepare($sql);

            if (!$statement instanceof mysqli_stmt) {
                throw new QueryExecutionException(
                    'Unable to prepare the export query.'
                );
            }

            if ($values !== []) {
                $statement->bind_param(
                    $this->bindingTypes($values),
                    ...$values
                );
            }

            $statement->execute();
            $result = $statement->get_result();

            while (($row = $result->fetch_assoc()) !== null) {
                yield $row;
            }
        } catch (QueryExecutionException $exception) {
            throw $exception;
        } catch (mysqli_sql_exception $exception) {
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
        } finally {
            $statement?->close();
        }
    }

    /**
     * @return array{string, array<int, mixed>}
     */
    private function prepareBindings(CompiledQuery $query): array
    {
        $sql = $query->sql;
        $values = [];

        $sql = preg_replace_callback(
            '/:([a-zA-Z_][a-zA-Z0-9_]*)/',
            static function (array $match) use ($query, &$values): string {
                $name = $match[1];

                if (!array_key_exists($name, $query->bindings)) {
                    throw new QueryExecutionException(
                        "Missing query binding: {$name}."
                    );
                }

                $values[] = $query->bindings[$name];

                return '?';
            },
            $sql
        );

        if ($sql === null) {
            throw new QueryExecutionException(
                'Unable to convert query bindings for mysqli.'
            );
        }

        return [$sql, $values];
    }

    /**
     * @param array<int, mixed> $values
     */
    private function bindingTypes(array $values): string
    {
        return implode('', array_map(
            static fn (mixed $value): string => match (true) {
                is_int($value), is_bool($value) => 'i',
                is_float($value) => 'd',
                default => 's',
            },
            $values
        ));
    }
}
