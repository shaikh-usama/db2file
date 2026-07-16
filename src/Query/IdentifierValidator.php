<?php

declare(strict_types=1);

namespace Db2File\Query;

use Db2File\Exceptions\InvalidIdentifierException;

final class IdentifierValidator
{
    public function validate(string $identifier): void
    {
        if ($identifier === '') {
            throw new InvalidIdentifierException(
                'A database identifier cannot be empty.'
            );
        }

        /*
         * Accepted:
         *
         * users
         * user_profiles
         * database.users
         *
         * Rejected:
         *
         * users;
         * users name
         * COUNT(*)
         * name AS customer_name
         */
        $isValid = preg_match(
            '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/',
            $identifier
        );

        if ($isValid !== 1) {
            throw new InvalidIdentifierException(
                "Invalid database identifier: {$identifier}"
            );
        }
    }

    public function validateAlias(string $alias): void
    {
        if (
            preg_match(
                '/^[A-Za-z_][A-Za-z0-9_]*$/',
                $alias
            ) !== 1
        ) {
            throw new InvalidIdentifierException(
                "Invalid database alias: {$alias}"
            );
        }
    }
}