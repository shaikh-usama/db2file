<?php

declare(strict_types=1);

namespace Db2File\Writers;

use Db2File\Contracts\WriterInterface;
use Db2File\Exceptions\FileWriteException;

final class CsvWriter implements WriterInterface
{
    public function format(): string
    {
        return 'csv';
    }

    public function extension(): string
    {
        return 'csv';
    }

    public function contentType(): string
    {
        return 'text/csv; charset=UTF-8';
    }

    /**
     * Save exported rows to a CSV file.
     *
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
        if ($headings === []) {
            throw new FileWriteException(
                'CSV export requires at least one heading.'
            );
        }

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

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new FileWriteException(
                "Unable to open CSV file for writing: {$path}"
            );
        }

        try {
            $this->writeToStream(
                headings: $headings,
                rows: $rows,
                stream: $handle,
                options: $options
            );
        } finally {
            fclose($handle);
        }
    }

    /**
     * Write CSV data to an existing writable stream.
     *
     * Examples:
     *
     * php://output
     * php://temp
     * fopen('/path/file.csv', 'wb')
     *
     * @param array<string, string> $headings
     * @param iterable<array<string, mixed>> $rows
     * @param resource $stream
     * @param array<string, mixed> $options
     */
    public function writeToStream(
        array $headings,
        iterable $rows,
        mixed $stream,
        array $options = []
    ): void {
        if ($headings === []) {
            throw new FileWriteException(
                'CSV export requires at least one heading.'
            );
        }

        if (!is_resource($stream)) {
            throw new FileWriteException(
                'CSV output must be a valid writable stream.'
            );
        }

        $metadata = stream_get_meta_data($stream);
        $mode = $metadata['mode'] ?? '';

        if (
            !str_contains($mode, 'w')
            && !str_contains($mode, 'a')
            && !str_contains($mode, '+')
        ) {
            throw new FileWriteException(
                'The supplied CSV stream is not writable.'
            );
        }

        $delimiter = $this->optionCharacter(
            options: $options,
            key: 'delimiter',
            default: ','
        );

        $enclosure = $this->optionCharacter(
            options: $options,
            key: 'enclosure',
            default: '"'
        );

        $escape = $this->optionCharacter(
            options: $options,
            key: 'escape',
            default: '\\'
        );

        $includeBom = (bool) (
            $options['bom']
            ?? $options['include_bom']
            ?? true
        );

        $protectFormulas = (bool) (
            $options['protect_formulas']
            ?? true
        );

        $nullValue = $options['null_value'] ?? '';

        if ($includeBom) {
            $written = fwrite(
                $stream,
                "\xEF\xBB\xBF"
            );

            if ($written === false) {
                throw new FileWriteException(
                    'Unable to write the UTF-8 BOM to the CSV output.'
                );
            }
        }

        $this->writeCsvRow(
            handle: $stream,
            values: array_values($headings),
            delimiter: $delimiter,
            enclosure: $enclosure,
            escape: $escape
        );

        foreach ($rows as $row) {
            $values = [];

            foreach (array_keys($headings) as $resultKey) {
                $values[] = $this->normalizeValue(
                    value: $row[$resultKey] ?? null,
                    nullValue: $nullValue,
                    protectFormulas: $protectFormulas
                );
            }

            $this->writeCsvRow(
                handle: $stream,
                values: $values,
                delimiter: $delimiter,
                enclosure: $enclosure,
                escape: $escape
            );
        }
    }

    /**
     * @param resource $handle
     * @param array<int, string|int|float> $values
     */
    private function writeCsvRow(
        mixed $handle,
        array $values,
        string $delimiter,
        string $enclosure,
        string $escape
    ): void {
        $result = fputcsv(
            $handle,
            $values,
            $delimiter,
            $enclosure,
            $escape
        );

        if ($result === false) {
            throw new FileWriteException(
                'Unable to write a row to the CSV output.'
            );
        }
    }

    private function normalizeValue(
        mixed $value,
        mixed $nullValue,
        bool $protectFormulas
    ): string|int|float {
        if ($value === null) {
            return $this->normalizeNullValue($nullValue);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $protectFormulas
                ? $this->protectSpreadsheetFormula($value)
                : $value;
        }

        if ($value instanceof \Stringable) {
            $stringValue = (string) $value;

            return $protectFormulas
                ? $this->protectSpreadsheetFormula($stringValue)
                : $stringValue;
        }

        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($encoded === false) {
            throw new FileWriteException(
                'Unable to convert a CSV value into a string.'
            );
        }

        return $protectFormulas
            ? $this->protectSpreadsheetFormula($encoded)
            : $encoded;
    }

    private function normalizeNullValue(
        mixed $nullValue
    ): string|int|float {
        if (
            is_string($nullValue)
            || is_int($nullValue)
            || is_float($nullValue)
        ) {
            return $nullValue;
        }

        if (is_bool($nullValue)) {
            return $nullValue ? '1' : '0';
        }

        return '';
    }

    private function protectSpreadsheetFormula(
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
        ) {
            return "'" . $value;
        }

        if (
            str_starts_with($value, "\t")
            || str_starts_with($value, "\r")
        ) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function optionCharacter(
        array $options,
        string $key,
        string $default
    ): string {
        $value = $options[$key] ?? $default;

        if (
            !is_string($value)
            || strlen($value) !== 1
        ) {
            throw new FileWriteException(
                "CSV option '{$key}' must be exactly one character."
            );
        }

        return $value;
    }
}