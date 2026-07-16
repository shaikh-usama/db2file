<?php

declare(strict_types=1);

namespace Db2File\Tests\Unit;

use Db2File\Writers\DocxWriter;
use Db2File\Writers\PdfWriter;
use Db2File\Writers\XlsxWriter;
use PHPUnit\Framework\TestCase;

final class DocumentWriterTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory =
            sys_get_temp_dir()
            . '/db2file-documents-'
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

    public function testItCreatesXlsxFile(): void
    {
        $path = $this->temporaryDirectory . '/users.xlsx';

        (new XlsxWriter())->save(
            headings: [
                'id' => 'ID',
                'name' => 'Name',
            ],
            rows: [
                [
                    'id' => 1,
                    'name' => 'Ali',
                ],
            ],
            path: $path
        );

        self::assertFileExists($path);
        self::assertGreaterThan(0, filesize($path));

        $contents = file_get_contents($path);

        self::assertIsString($contents);
        self::assertStringStartsWith('PK', $contents);
    }

    public function testItCreatesDocxFile(): void
    {
        $path = $this->temporaryDirectory . '/users.docx';

        (new DocxWriter())->save(
            headings: [
                'id' => 'ID',
                'name' => 'Name',
            ],
            rows: [
                [
                    'id' => 1,
                    'name' => 'Ali',
                ],
            ],
            path: $path
        );

        self::assertFileExists($path);
        self::assertGreaterThan(0, filesize($path));

        $contents = file_get_contents($path);

        self::assertIsString($contents);
        self::assertStringStartsWith('PK', $contents);
    }

    public function testItCreatesPdfFile(): void
    {
        $path = $this->temporaryDirectory . '/users.pdf';

        (new PdfWriter())->save(
            headings: [
                'id' => 'ID',
                'name' => 'Name',
            ],
            rows: [
                [
                    'id' => 1,
                    'name' => 'Ali',
                ],
            ],
            path: $path
        );

        self::assertFileExists($path);
        self::assertGreaterThan(0, filesize($path));

        $contents = file_get_contents($path);

        self::assertIsString($contents);
        self::assertStringStartsWith(
            '%PDF-',
            $contents
        );
    }
}