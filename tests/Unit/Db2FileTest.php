<?php

declare(strict_types=1);

namespace Db2File\Tests\Unit;

use Db2File\Db2File;
use mysqli;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class Db2FileTest extends TestCase
{
    public function testItAcceptsMysqliConnection(): void
    {
        $connection = (new ReflectionClass(mysqli::class))
            ->newInstanceWithoutConstructor();

        $builder = Db2File::make($connection);

        self::assertSame($connection, $builder->connection());
    }
}
