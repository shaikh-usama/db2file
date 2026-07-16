<?php

declare(strict_types=1);

namespace Db2File\Database;

use Db2File\Exceptions\QueryBuildException;
use Db2File\Query\Aggregate;
use Db2File\Query\BetweenCondition;
use Db2File\Query\CompiledQuery;
use Db2File\Query\Condition;
use Db2File\Query\HavingCondition;
use Db2File\Query\ListCondition;
use Db2File\Query\NullCondition;
use Db2File\Query\QueryState;

final class MySqlCompiler
{
    private const SUPPORTED_OPERATORS = [
        '=',
        '!=',
        '<>',
        '>',
        '>=',
        '<',
        '<=',
        'LIKE',
        'NOT LIKE',
    ];

    public function __construct(
        private readonly MySqlIdentifierQuoter $quoter
    ) {
    }

    public function compile(QueryState $state): CompiledQuery
    {
        $this->validateState($state);

        $bindings = [];

        $sql = $this->compileSelect($state);

        $sql .= ' FROM ' . $this->quoter->quote(
            (string) $state->table
        );

        $sql .= $this->compileWhere(
            $state,
            $bindings
        );

        $sql .= $this->compileGroupBy($state);

        $sql .= $this->compileHaving(
            $state,
            $bindings
        );

        $sql .= $this->compileOrderBy($state);

        $sql .= $this->compileLimitAndOffset($state);

        return new CompiledQuery(
            sql: $sql,
            bindings: $bindings
        );
    }

    private function validateState(QueryState $state): void
    {
        if ($state->table === null) {
            throw new QueryBuildException(
                'A table must be selected before compiling the query.'
            );
        }

        if (
            $state->selectedColumns === []
            && $state->aggregates === []
        ) {
            throw new QueryBuildException(
                'Select at least one column or aggregate function.'
            );
        }

        $this->validateAggregateGrouping($state);

        $this->validateHavingAggregates($state);
    }

    private function validateAggregateGrouping(
        QueryState $state
    ): void {
        if ($state->aggregates === []) {
            return;
        }

        foreach ($state->selectedColumns as $column) {
            if (!in_array($column, $state->groupBy, true)) {
                throw new QueryBuildException(
                    "Selected column '{$column}' must appear in GROUP BY "
                    . 'when aggregate functions are used.'
                );
            }
        }
    }

    private function validateHavingAggregates(
        QueryState $state
    ): void {
        foreach ($state->having as $having) {
            $exists = false;

            foreach ($state->aggregates as $aggregate) {
                if (
                    $aggregate->function === $having->function
                    && $aggregate->column === $having->column
                    && $aggregate->distinct === $having->distinct
                ) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                throw new QueryBuildException(
                    sprintf(
                        'HAVING uses %s(%s), but that aggregate is not selected.',
                        $having->function->value,
                        $having->column
                    )
                );
            }
        }
    }

    private function compileSelect(QueryState $state): string
    {
        $parts = [];

        foreach ($state->selectedColumns as $column) {
            $parts[] = $this->quoter->quote($column);
        }

        foreach ($state->aggregates as $aggregate) {
            $parts[] = $this->compileAggregate($aggregate);
        }

        $distinct = $state->distinct
            ? ' DISTINCT'
            : '';

        return sprintf(
            'SELECT%s %s',
            $distinct,
            implode(', ', $parts)
        );
    }

    private function compileAggregate(
        Aggregate $aggregate
    ): string {
        if ($aggregate->column === '*') {
            $column = '*';
        } else {
            $column = $this->quoter->quote(
                $aggregate->column
            );
        }

        if ($aggregate->distinct) {
            $column = 'DISTINCT ' . $column;
        }

        return sprintf(
            '%s(%s) AS %s',
            $aggregate->function->value,
            $column,
            $this->quoter->quoteAlias($aggregate->alias)
        );
    }

    /**
     * @param array<string, mixed> $bindings
     */
    private function compileWhere(
        QueryState $state,
        array &$bindings
    ): string {
        if ($state->conditions === []) {
            return '';
        }

        $parts = [];

        foreach ($state->conditions as $index => $condition) {
            $compiledCondition = $this->compileWhereCondition(
                $condition,
                $index,
                $bindings
            );

            if ($index === 0) {
                $parts[] = $compiledCondition;
            } else {
                $parts[] = sprintf(
                    '%s %s',
                    $condition->boolean,
                    $compiledCondition
                );
            }
        }

        return ' WHERE ' . implode(' ', $parts);
    }

    /**
     * @param Condition|BetweenCondition|ListCondition|NullCondition $condition
     * @param array<string, mixed> $bindings
     */
    private function compileWhereCondition(
        object $condition,
        int $index,
        array &$bindings
    ): string {
        return match (true) {
            $condition instanceof Condition =>
                $this->compileBasicCondition(
                    $condition,
                    $index,
                    $bindings
                ),

            $condition instanceof BetweenCondition =>
                $this->compileBetweenCondition(
                    $condition,
                    $index,
                    $bindings
                ),

            $condition instanceof ListCondition =>
                $this->compileListCondition(
                    $condition,
                    $index,
                    $bindings
                ),

            $condition instanceof NullCondition =>
                $this->compileNullCondition($condition),

            default => throw new QueryBuildException(
                'Unsupported WHERE condition type: '
                . $condition::class
            ),
        };
    }

    /**
     * @param array<string, mixed> $bindings
     */
    private function compileBasicCondition(
        Condition $condition,
        int $index,
        array &$bindings
    ): string {
        $this->assertOperator($condition->operator);

        $parameter = 'where_' . $index;

        $bindings[$parameter] = $condition->value;

        return sprintf(
            '%s %s :%s',
            $this->quoter->quote($condition->column),
            $condition->operator,
            $parameter
        );
    }

    /**
     * @param array<string, mixed> $bindings
     */
    private function compileBetweenCondition(
        BetweenCondition $condition,
        int $index,
        array &$bindings
    ): string {
        $fromParameter = 'where_' . $index . '_from';
        $toParameter = 'where_' . $index . '_to';

        $bindings[$fromParameter] = $condition->from;
        $bindings[$toParameter] = $condition->to;

        $operator = $condition->negated
            ? 'NOT BETWEEN'
            : 'BETWEEN';

        return sprintf(
            '%s %s :%s AND :%s',
            $this->quoter->quote($condition->column),
            $operator,
            $fromParameter,
            $toParameter
        );
    }

    /**
     * @param array<string, mixed> $bindings
     */
    private function compileListCondition(
        ListCondition $condition,
        int $index,
        array &$bindings
    ): string {
        if ($condition->values === []) {
            throw new QueryBuildException(
                'An IN condition cannot contain an empty value list.'
            );
        }

        $placeholders = [];

        foreach ($condition->values as $valueIndex => $value) {
            $parameter = sprintf(
                'where_%d_%d',
                $index,
                $valueIndex
            );

            $placeholders[] = ':' . $parameter;
            $bindings[$parameter] = $value;
        }

        $operator = $condition->negated
            ? 'NOT IN'
            : 'IN';

        return sprintf(
            '%s %s (%s)',
            $this->quoter->quote($condition->column),
            $operator,
            implode(', ', $placeholders)
        );
    }

    private function compileNullCondition(
        NullCondition $condition
    ): string {
        $operator = $condition->negated
            ? 'IS NOT NULL'
            : 'IS NULL';

        return sprintf(
            '%s %s',
            $this->quoter->quote($condition->column),
            $operator
        );
    }

    private function compileGroupBy(
        QueryState $state
    ): string {
        if ($state->groupBy === []) {
            return '';
        }

        $columns = array_map(
            fn (string $column): string =>
                $this->quoter->quote($column),
            $state->groupBy
        );

        return ' GROUP BY ' . implode(', ', $columns);
    }

    /**
     * @param array<string, mixed> $bindings
     */
    private function compileHaving(
        QueryState $state,
        array &$bindings
    ): string {
        if ($state->having === []) {
            return '';
        }

        $parts = [];

        foreach ($state->having as $index => $having) {
            $compiled = $this->compileHavingCondition(
                $having,
                $index,
                $bindings
            );

            if ($index === 0) {
                $parts[] = $compiled;
            } else {
                $parts[] = sprintf(
                    '%s %s',
                    $having->boolean,
                    $compiled
                );
            }
        }

        return ' HAVING ' . implode(' ', $parts);
    }

    /**
     * @param array<string, mixed> $bindings
     */
    private function compileHavingCondition(
        HavingCondition $condition,
        int $index,
        array &$bindings
    ): string {
        $this->assertOperator($condition->operator);

        if ($condition->column === '*') {
            $column = '*';
        } else {
            $column = $this->quoter->quote(
                $condition->column
            );
        }

        if ($condition->distinct) {
            $column = 'DISTINCT ' . $column;
        }

        $parameter = 'having_' . $index;

        $bindings[$parameter] = $condition->value;

        return sprintf(
            '%s(%s) %s :%s',
            $condition->function->value,
            $column,
            $condition->operator,
            $parameter
        );
    }

    private function compileOrderBy(
        QueryState $state
    ): string {
        if ($state->orders === []) {
            return '';
        }

        $parts = [];

        foreach ($state->orders as $order) {
            $parts[] = sprintf(
                '%s %s',
                $this->quoter->quote($order->column),
                $order->direction
            );
        }

        return ' ORDER BY ' . implode(', ', $parts);
    }

    private function compileLimitAndOffset(
        QueryState $state
    ): string {
        if ($state->limit === null) {
            if ($state->offset > 0) {
                throw new QueryBuildException(
                    'MySQL requires a limit when an offset is used.'
                );
            }

            return '';
        }

        $sql = ' LIMIT ' . $state->limit;

        if ($state->offset > 0) {
            $sql .= ' OFFSET ' . $state->offset;
        }

        return $sql;
    }

    private function assertOperator(string $operator): void
    {
        if (
            !in_array(
                strtoupper($operator),
                self::SUPPORTED_OPERATORS,
                true
            )
        ) {
            throw new QueryBuildException(
                "Unsupported SQL operator: {$operator}"
            );
        }
    }
}