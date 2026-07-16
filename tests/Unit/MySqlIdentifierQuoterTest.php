<?php

declare(strict_types=1);

namespace Db2File\Tests\Unit;

use Db2File\Database\MySqlIdentifierQuoter;
use Db2File\Query\IdentifierValidator;
use PHPUnit\Framework\TestCase;

final class MySqlIdentifierQuoterTest extends TestCase
{
    private MySqlIdentifierQuoter $quoter;

    protected function setUp(): void
    {
        $this->quoter = new MySqlIdentifierQuoter(
            new IdentifierValidator()
        );
    }

    public function testItQuotesSimpleIdentifier(): void
    {
        self::assertSame(
            '`users`',
            $this->quoter->quote('users')
        );
    }

    public function testItQuotesQualifiedIdentifier(): void
    {
        self::assertSame(
            '`application`.`users`',
            $this->quoter->quote('application.users')
        );
    }

    public function testItQuotesAlias(): void
    {
        self::assertSame(
            '`total_revenue`',
            $this->quoter->quoteAlias('total_revenue')
        );
    }
}