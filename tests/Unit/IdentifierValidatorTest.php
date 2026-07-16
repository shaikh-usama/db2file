<?php

declare(strict_types=1);

namespace Db2File\Tests\Unit;

use Db2File\Exceptions\InvalidIdentifierException;
use Db2File\Query\IdentifierValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdentifierValidatorTest extends TestCase
{
    private IdentifierValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new IdentifierValidator();
    }

    #[DataProvider('validIdentifiers')]
    public function testItAcceptsValidIdentifiers(
        string $identifier
    ): void {
        $this->validator->validate($identifier);

        self::assertTrue(true);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validIdentifiers(): array
    {
        return [
            'simple table' => ['users'],
            'underscore' => ['user_profiles'],
            'numbers after first character' => ['orders2026'],
            'database table' => ['application.users'],
        ];
    }

    #[DataProvider('invalidIdentifiers')]
    public function testItRejectsInvalidIdentifiers(
        string $identifier
    ): void {
        $this->expectException(
            InvalidIdentifierException::class
        );

        $this->validator->validate($identifier);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIdentifiers(): array
    {
        return [
            'empty' => [''],
            'starts with number' => ['123users'],
            'contains space' => ['user profiles'],
            'sql injection' => ['users; DROP TABLE users'],
            'sql expression' => ['COUNT(*)'],
            'sql alias' => ['name AS customer'],
            'comment' => ['users--'],
            'multiple dots' => ['database.schema.users'],
            'backticks' => ['`users`'],
        ];
    }

    #[DataProvider('validAliases')]
    public function testItAcceptsValidAliases(
        string $alias
    ): void {
        $this->validator->validateAlias($alias);

        self::assertTrue(true);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validAliases(): array
    {
        return [
            'simple' => ['total'],
            'underscore' => ['total_revenue'],
            'numbers' => ['total2026'],
        ];
    }

    #[DataProvider('invalidAliases')]
    public function testItRejectsInvalidAliases(
        string $alias
    ): void {
        $this->expectException(
            InvalidIdentifierException::class
        );

        $this->validator->validateAlias($alias);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidAliases(): array
    {
        return [
            'empty' => [''],
            'space' => ['total revenue'],
            'dot' => ['orders.total'],
            'expression' => ['SUM(total)'],
            'special character' => ['total-revenue'],
        ];
    }
}