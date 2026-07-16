<?php

declare(strict_types=1);

namespace Db2File\Database;

use Db2File\Query\IdentifierValidator;

final class MySqlIdentifierQuoter
{
    public function __construct(
        private readonly IdentifierValidator $validator
    ) {
    }

    public function quote(string $identifier): string
    {
        $this->validator->validate($identifier);

        $parts = explode('.', $identifier);

        return implode(
            '.',
            array_map(
                fn (string $part): string => sprintf(
                    '`%s`',
                    str_replace('`', '``', $part)
                ),
                $parts
            )
        );
    }

    public function quoteAlias(string $alias): string
    {
        $this->validator->validateAlias($alias);

        return sprintf(
            '`%s`',
            str_replace('`', '``', $alias)
        );
    }
}