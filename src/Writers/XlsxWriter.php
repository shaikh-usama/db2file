<?php

declare(strict_types=1);

namespace Db2File\Writers;

use Db2File\Contracts\WriterInterface;
use Db2File\Exceptions\FileWriteException;
use Db2File\Exceptions\MissingDependencyException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

final class XlsxWriter implements WriterInterface
{
    public function format(): string
    {
        return 'xlsx';
    }

    public function extension(): string
    {
        return 'xlsx';
    }

    public function contentType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    /**
     * @param array<string, string> $headings
     * @param iterable<array<string, mixed>> $rows
     * @param array<string, mixed> $options
     */
    public function save(
        array $headings,
        iterable $rows,
        string $path,
        array $options = []
    ): void {
        $this->ensureDependencyExists();

        if ($headings === []) {
            throw new FileWriteException(
                'XLSX export requires at least one heading.'
            );
        }

        $this->createDirectory($path);

        $spreadsheet = new Spreadsheet();

        try {
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->setTitle(
                $this->sanitizeSheetName(
                    (string) ($options['sheet_name'] ?? 'Export')
                )
            );

            $this->configureDocumentProperties(
                $spreadsheet,
                $options
            );

            $this->writeHeadings(
                $sheet,
                $headings
            );

            $lastRow = $this->writeRows(
                $sheet,
                $headings,
                $rows,
                $options
            );

            $this->configureWorksheet(
                $sheet,
                count($headings),
                $lastRow,
                $options
            );

            $writer = new Xlsx($spreadsheet);
            $writer->save($path);
        } catch (FileWriteException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new FileWriteException(
                'Unable to create the XLSX file: '
                . $exception->getMessage(),
                previous: $exception
            );
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    private function ensureDependencyExists(): void
    {
        if (!class_exists(Spreadsheet::class)) {
            throw new MissingDependencyException(
                'XLSX export requires phpoffice/phpspreadsheet. '
                . 'Install it with: composer require phpoffice/phpspreadsheet'
            );
        }
    }

    private function createDirectory(
        string $path
    ): void {
        $directory = dirname($path);

        if (
            $directory !== '.'
            && !is_dir($directory)
            && !mkdir($directory, 0775, true)
            && !is_dir($directory)
        ) {
            throw new FileWriteException(
                "Unable to create export directory: {$directory}"
            );
        }
    }

    /**
     * @param array<string, string> $headings
     */
    private function writeHeadings(
        Worksheet $sheet,
        array $headings
    ): void {
        $columnNumber = 1;

        foreach ($headings as $heading) {
            $cell = Coordinate::stringFromColumnIndex(
                $columnNumber
            ) . '1';

            $sheet->setCellValueExplicit(
                $cell,
                $heading,
                DataType::TYPE_STRING
            );

            $columnNumber++;
        }
    }

    /**
     * @param array<string, string> $headings
     * @param iterable<array<string, mixed>> $rows
     * @param array<string, mixed> $options
     */
    private function writeRows(
        Worksheet $sheet,
        array $headings,
        iterable $rows,
        array $options
    ): int {
        $rowNumber = 2;

        $protectFormulas = (bool) (
            $options['protect_formulas'] ?? true
        );

        $nullValue = $options['null_value'] ?? '';

        foreach ($rows as $row) {
            $columnNumber = 1;

            foreach (array_keys($headings) as $resultKey) {
                $cell = Coordinate::stringFromColumnIndex(
                    $columnNumber
                ) . $rowNumber;

                $value = $row[$resultKey] ?? null;

                $this->writeCell(
                    sheet: $sheet,
                    cell: $cell,
                    value: $value,
                    nullValue: $nullValue,
                    protectFormulas: $protectFormulas
                );

                $columnNumber++;
            }

            $rowNumber++;
        }

        return max(1, $rowNumber - 1);
    }

    private function writeCell(
        Worksheet $sheet,
        string $cell,
        mixed $value,
        mixed $nullValue,
        bool $protectFormulas
    ): void {
        if ($value === null) {
            $sheet->setCellValueExplicit(
                $cell,
                $this->normalizeNullValue($nullValue),
                DataType::TYPE_STRING
            );

            return;
        }

        if (is_bool($value)) {
            $sheet->setCellValueExplicit(
                $cell,
                $value,
                DataType::TYPE_BOOL
            );

            return;
        }

        if (is_int($value) || is_float($value)) {
            $sheet->setCellValueExplicit(
                $cell,
                $value,
                DataType::TYPE_NUMERIC
            );

            return;
        }

        $stringValue = $this->normalizeStringValue(
            $value
        );

        if ($protectFormulas) {
            $stringValue = $this->protectFormula(
                $stringValue
            );
        }

        /*
         * Explicit strings preserve leading zeroes in values such as
         * phone numbers, account numbers and postal codes.
         */
        $sheet->setCellValueExplicit(
            $cell,
            $stringValue,
            DataType::TYPE_STRING
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function configureWorksheet(
        Worksheet $sheet,
        int $columnCount,
        int $lastRow,
        array $options
    ): void {
        $lastColumn = Coordinate::stringFromColumnIndex(
            $columnCount
        );

        $headingRange = "A1:{$lastColumn}1";
        $fullRange = "A1:{$lastColumn}{$lastRow}";

        $headingStyle = [
            'font' => [
                'bold' => true,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'argb' => 'FFEFEFEF',
                ],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => [
                        'argb' => 'FFB7B7B7',
                    ],
                ],
            ],
        ];

        $sheet
            ->getStyle($headingRange)
            ->applyFromArray($headingStyle);

        $sheet
            ->getStyle($fullRange)
            ->getAlignment()
            ->setVertical(
                Alignment::VERTICAL_TOP
            );

        if (($options['wrap_text'] ?? true) === true) {
            $sheet
                ->getStyle($fullRange)
                ->getAlignment()
                ->setWrapText(true);
        }

        if (($options['freeze_header'] ?? true) === true) {
            $sheet->freezePane('A2');
        }

        if (($options['auto_filter'] ?? true) === true) {
            $sheet->setAutoFilter($headingRange);
        }

        if (($options['auto_size'] ?? true) === true) {
            for (
                $columnNumber = 1;
                $columnNumber <= $columnCount;
                $columnNumber++
            ) {
                $column = Coordinate::stringFromColumnIndex(
                    $columnNumber
                );

                $sheet
                    ->getColumnDimension($column)
                    ->setAutoSize(true);
            }
        }

        $sheet
            ->getRowDimension(1)
            ->setRowHeight(
                (float) ($options['header_height'] ?? 24)
            );

        if (($options['right_to_left'] ?? false) === true) {
            $sheet->setRightToLeft(true);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function configureDocumentProperties(
        Spreadsheet $spreadsheet,
        array $options
    ): void {
        $properties = $spreadsheet->getProperties();

        if (isset($options['creator'])) {
            $properties->setCreator(
                (string) $options['creator']
            );
        }

        if (isset($options['last_modified_by'])) {
            $properties->setLastModifiedBy(
                (string) $options['last_modified_by']
            );
        }

        if (isset($options['title'])) {
            $properties->setTitle(
                (string) $options['title']
            );
        }

        if (isset($options['subject'])) {
            $properties->setSubject(
                (string) $options['subject']
            );
        }

        if (isset($options['description'])) {
            $properties->setDescription(
                (string) $options['description']
            );
        }

        if (isset($options['keywords'])) {
            $properties->setKeywords(
                (string) $options['keywords']
            );
        }

        if (isset($options['category'])) {
            $properties->setCategory(
                (string) $options['category']
            );
        }
    }

    private function sanitizeSheetName(
        string $name
    ): string {
        $name = trim($name);

        if ($name === '') {
            return 'Export';
        }

        $name = preg_replace(
            '/[\\\\\\/?*\\[\\]:]/',
            '-',
            $name
        );

        if (!is_string($name) || $name === '') {
            return 'Export';
        }

        /*
         * Excel worksheet names have a 31-character limit.
         */
        return function_exists('mb_substr')
            ? mb_substr($name, 0, 31, 'UTF-8')
            : substr($name, 0, 31);
    }

    private function normalizeStringValue(
        mixed $value
    ): string {
        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($encoded === false) {
            throw new FileWriteException(
                'Unable to convert an XLSX value into a string.'
            );
        }

        return $encoded;
    }

    private function normalizeNullValue(
        mixed $value
    ): string {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    private function protectFormula(
        string $value
    ): string {
        if ($value === '') {
            return $value;
        }

        if (
            in_array(
                $value[0],
                ['=', '+', '-', '@'],
                true
            )
            || str_starts_with($value, "\t")
            || str_starts_with($value, "\r")
        ) {
            return "'" . $value;
        }

        return $value;
    }
}