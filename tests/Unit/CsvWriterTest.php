<?php

declare(strict_types=1);

namespace Db2File\Tests\Unit;

use Db2File\Writers\CsvWriter;
use PHPUnit\Framework\TestCase;

final class CsvWriterTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory =
            sys_get_temp_dir()
            . '/db2file-tests-'
            . bin2hex(random_bytes(6));

        mkdir(
            $this->temporaryDirectory,
            0775,
            true
        );
    }

    protected function tearDown(): void
    {
        foreach (
            glob($this->temporaryDirectory . '/*') ?: []
            as $file
        ) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->temporaryDirectory)) {
            rmdir($this->temporaryDirectory);
        }
    }

    public function testItCreatesCsvWithCustomHeadings(): void
    {
        $path = $this->temporaryDirectory . '/users.csv';

        $writer = new CsvWriter();

        $writer->save(
            headings: [
                'id' => 'User ID',
                'name' => 'Customer Name',
            ],
            rows: [
                [
                    'id' => 1,
                    'name' => 'Ali Khan',
                ],
                [
                    'id' => 2,
                    'name' => 'Sara Ahmed',
                ],
            ],
            path: $path,
            options: [
                'bom' => false,
            ]
        );

        self::assertFileExists($path);

        $contents = file_get_contents($path);

        self::assertIsString($contents);
        self::assertStringContainsString(
            "\"User ID\",\"Customer Name\"",
            $contents
        );
        self::assertStringContainsString(
            "1,\"Ali Khan\"",
            $contents
        );
    }

    public function testItProtectsSpreadsheetFormulas(): void
    {
        $path = $this->temporaryDirectory . '/formula.csv';

        $writer = new CsvWriter();

        $writer->save(
            headings: [
                'value' => 'Value',
            ],
            rows: [
                [
                    'value' => '=1+1',
                ],
            ],
            path: $path,
            options: [
                'bom' => false,
                'protect_formulas' => true,
            ]
        );

        $contents = file_get_contents($path);

        self::assertIsString($contents);
        self::assertStringContainsString(
            "'=1+1",
            $contents
        );
    }

    public function testItWritesUtf8BomByDefault(): void
    {
        $path = $this->temporaryDirectory . '/bom.csv';

        $writer = new CsvWriter();

        $writer->save(
            headings: [
                'name' => 'Name',
            ],
            rows: [
                [
                    'name' => 'Ali',
                ],
            ],
            path: $path
        );

        $contents = file_get_contents($path);

        self::assertIsString($contents);
        self::assertStringStartsWith(
            "\xEF\xBB\xBF",
            $contents
        );
    }
}