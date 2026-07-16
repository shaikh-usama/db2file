<?php

declare(strict_types=1);

namespace Db2File\Query;

use Db2File\Exceptions\InvalidArgumentException;

enum AggregateFunction: string
{
    case Count = 'COUNT';
    case Sum = 'SUM';
    case Average = 'AVG';
    case Minimum = 'MIN';
    case Maximum = 'MAX';

    public static function fromString(string $function): self
    {
        return match (strtoupper(trim($function))) {
            'COUNT' => self::Count,
            'SUM' => self::Sum,
            'AVG', 'AVERAGE' => self::Average,
            'MIN', 'MINIMUM' => self::Minimum,
            'MAX', 'MAXIMUM' => self::Maximum,

            default => throw new InvalidArgumentException(
                "Unsupported aggregate function: {$function}"
            ),
        };
    }
}