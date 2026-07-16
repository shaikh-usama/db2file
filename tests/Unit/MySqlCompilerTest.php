<?php

declare(strict_types=1);

namespace Db2File\Tests\Unit;

use Db2File\Database\MySqlCompiler;
use Db2File\Database\MySqlIdentifierQuoter;
use Db2File\Query\Aggregate;
use Db2File\Query\AggregateFunction;
use Db2File\Query\BetweenCondition;
use Db2File\Query\Condition;
use Db2File\Query\HavingCondition;
use Db2File\Query\IdentifierValidator;
use Db2File\Query\Order;
use Db2File\Query\QueryState;
use PHPUnit\Framework\TestCase;

final class MySqlCompilerTest extends TestCase
{
    private MySqlCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new MySqlCompiler(
            new MySqlIdentifierQuoter(
                new IdentifierValidator()
            )
        );
    }

    public function testItCompilesNormalSelectQuery(): void
    {
        $state = new QueryState(
            table: 'users',
            selectedColumns: [
                'id',
                'name',
                'email',
            ],
            conditions: [
                new Condition(
                    'AND',
                    'status',
                    '=',
                    'active'
                ),
            ],
            orders: [
                new Order(
                    'created_at',
                    'DESC'
                ),
            ],
            limit: 10
        );

        $compiled = $this->compiler->compile($state);

        self::assertSame(
            'SELECT `id`, `name`, `email` '
            . 'FROM `users` '
            . 'WHERE `status` = :where_0 '
            . 'ORDER BY `created_at` DESC '
            . 'LIMIT 10',
            $compiled->sql
        );

        self::assertSame(
            [
                'where_0' => 'active',
            ],
            $compiled->bindings
        );
    }

    public function testItCompilesBetweenCondition(): void
    {
        $state = new QueryState(
            table: 'orders',
            selectedColumns: [
                'id',
                'created_at',
            ],
            conditions: [
                new BetweenCondition(
                    'AND',
                    'created_at',
                    '2026-01-01',
                    '2026-12-31'
                ),
            ],
            limit: 100
        );

        $compiled = $this->compiler->compile($state);

        self::assertSame(
            'SELECT `id`, `created_at` '
            . 'FROM `orders` '
            . 'WHERE `created_at` '
            . 'BETWEEN :where_0_from AND :where_0_to '
            . 'LIMIT 100',
            $compiled->sql
        );

        self::assertSame(
            [
                'where_0_from' => '2026-01-01',
                'where_0_to' => '2026-12-31',
            ],
            $compiled->bindings
        );
    }

    public function testItCompilesAggregateQuery(): void
    {
        $state = new QueryState(
            table: 'orders',
            selectedColumns: [
                'status',
            ],
            aggregates: [
                new Aggregate(
                    AggregateFunction::Count,
                    'id',
                    'count_id',
                    'Total Orders'
                ),
                new Aggregate(
                    AggregateFunction::Sum,
                    'total',
                    'sum_total',
                    'Total Revenue'
                ),
            ],
            groupBy: [
                'status',
            ],
            having: [
                new HavingCondition(
                    'AND',
                    AggregateFunction::Sum,
                    'total',
                    '>',
                    1000
                ),
            ],
            limit: 100
        );

        $compiled = $this->compiler->compile($state);

        self::assertSame(
            'SELECT `status`, '
            . 'COUNT(`id`) AS `count_id`, '
            . 'SUM(`total`) AS `sum_total` '
            . 'FROM `orders` '
            . 'GROUP BY `status` '
            . 'HAVING SUM(`total`) > :having_0 '
            . 'LIMIT 100',
            $compiled->sql
        );

        self::assertSame(
            [
                'having_0' => 1000,
            ],
            $compiled->bindings
        );
    }
}