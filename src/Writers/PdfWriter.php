<?php

declare(strict_types=1);

namespace Db2File\Writers;

use Db2File\Contracts\WriterInterface;
use Db2File\Exceptions\FileWriteException;
use Db2File\Exceptions\MissingDependencyException;
use Dompdf\Dompdf;
use Dompdf\Options;
use Throwable;

final class PdfWriter implements WriterInterface
{
    public function format(): string
    {
        return 'pdf';
    }

    public function extension(): string
    {
        return 'pdf';
    }

    public function contentType(): string
    {
        return 'application/pdf';
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
                'PDF export requires at least one heading.'
            );
        }

        $this->createDirectory($path);

        try {
            $dompdfOptions = new Options();

            $dompdfOptions->set(
                'defaultFont',
                (string) ($options['font'] ?? 'DejaVu Sans')
            );

            /*
             * Keep remote resources disabled by default.
             */
            $dompdfOptions->set(
                'isRemoteEnabled',
                (bool) ($options['remote_enabled'] ?? false)
            );

            $dompdfOptions->set(
                'isHtml5ParserEnabled',
                true
            );

            $dompdf = new Dompdf($dompdfOptions);

            $html = $this->buildHtml(
                headings: $headings,
                rows: $rows,
                options: $options
            );

            $dompdf->loadHtml(
                $html,
                'UTF-8'
            );

            $dompdf->setPaper(
                (string) ($options['paper'] ?? 'A4'),
                (string) ($options['orientation'] ?? 'landscape')
            );

            $dompdf->render();

            $contents = $dompdf->output();

            if ($contents === '') {
                throw new FileWriteException(
                    'Dompdf generated an empty PDF file.'
                );
            }

            $written = file_put_contents(
                $path,
                $contents
            );

            if ($written === false) {
                throw new FileWriteException(
                    "Unable to write PDF file: {$path}"
                );
            }
        } catch (FileWriteException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new FileWriteException(
                'Unable to create the PDF file: '
                . $exception->getMessage(),
                previous: $exception
            );
        }
    }

    private function ensureDependencyExists(): void
    {
        if (!class_exists(Dompdf::class)) {
            throw new MissingDependencyException(
                'PDF export requires dompdf/dompdf. '
                . 'Install it with: composer require dompdf/dompdf'
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
     * @param iterable<array<string, mixed>> $rows
     * @param array<string, mixed> $options
     */
    private function buildHtml(
        array $headings,
        iterable $rows,
        array $options
    ): string {
        $title = $this->escape(
            (string) ($options['title'] ?? 'Database Export')
        );

        $subtitle = isset($options['subtitle'])
            ? $this->escape((string) $options['subtitle'])
            : '';

        $generatedAt = (bool) (
            $options['generated_at'] ?? true
        );

        $showRowNumbers = (bool) (
            $options['row_numbers'] ?? false
        );

        $fontSize = $this->positiveNumberOption(
            $options,
            'font_size',
            9
        );

        $headingFontSize = $this->positiveNumberOption(
            $options,
            'heading_font_size',
            9
        );

        $titleFontSize = $this->positiveNumberOption(
            $options,
            'title_font_size',
            18
        );

        $cellPadding = $this->positiveNumberOption(
            $options,
            'cell_padding',
            5
        );

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    @page {
        margin: 24px;
    }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: {$fontSize}px;
        color: #222;
    }

    h1 {
        margin: 0 0 6px 0;
        font-size: {$titleFontSize}px;
    }

    .subtitle {
        margin-bottom: 12px;
        color: #555;
    }

    .meta {
        margin-bottom: 12px;
        font-size: 8px;
        color: #666;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    thead {
        display: table-header-group;
    }

    tr {
        page-break-inside: avoid;
    }

    th,
    td {
        border: 1px solid #888;
        padding: {$cellPadding}px;
        vertical-align: top;
        overflow-wrap: anywhere;
        word-wrap: break-word;
    }

    th {
        font-size: {$headingFontSize}px;
        font-weight: bold;
        background: #ededed;
        text-align: left;
    }

    tbody tr:nth-child(even) {
        background: #fafafa;
    }

    .row-number {
        width: 32px;
        text-align: center;
    }
</style>
</head>
<body>
<h1>{$title}</h1>
HTML;

        if ($subtitle !== '') {
            $html .= '<div class="subtitle">'
                . $subtitle
                . '</div>';
        }

        if ($generatedAt) {
            $html .= '<div class="meta">Generated at: '
                . $this->escape(date('Y-m-d H:i:s'))
                . '</div>';
        }

        $html .= '<table><thead><tr>';

        if ($showRowNumbers) {
            $html .= '<th class="row-number">#</th>';
        }

        foreach ($headings as $heading) {
            $html .= '<th>'
                . $this->escape($heading)
                . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        $rowNumber = 1;

        foreach ($rows as $row) {
            $html .= '<tr>';

            if ($showRowNumbers) {
                $html .= '<td class="row-number">'
                    . $rowNumber
                    . '</td>';
            }

            foreach (array_keys($headings) as $resultKey) {
                $html .= '<td>'
                    . $this->escape(
                        $this->normalizeValue(
                            $row[$resultKey] ?? null,
                            $options['null_value'] ?? ''
                        )
                    )
                    . '</td>';
            }

            $html .= '</tr>';

            $rowNumber++;
        }

        $html .= '</tbody></table></body></html>';

        return $html;
    }

    private function normalizeValue(
        mixed $value,
        mixed $nullValue
    ): string {
        if ($value === null) {
            return is_scalar($nullValue)
                ? (string) $nullValue
                : '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
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
                'Unable to convert a PDF value into a string.'
            );
        }

        return $encoded;
    }

    private function escape(
        string $value
    ): string {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function positiveNumberOption(
        array $options,
        string $key,
        int|float $default
    ): int|float {
        $value = $options[$key] ?? $default;

        if (
            !is_numeric($value)
            || (float) $value <= 0
        ) {
            throw new FileWriteException(
                "PDF option '{$key}' must be greater than zero."
            );
        }

        return (float) $value;
    }
}