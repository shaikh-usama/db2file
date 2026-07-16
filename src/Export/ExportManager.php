<?php

declare(strict_types=1);

namespace Db2File\Export;

use Db2File\Exceptions\DownloadException;
use Db2File\Exceptions\FileWriteException;
use Db2File\Exceptions\InvalidArgumentException;
use Db2File\Query\QueryBuilder;
use Db2File\Writers\CsvWriter;
use Db2File\Transform\TransformPipeline;
use Db2File\Writers\XlsxWriter;
use Db2File\Exceptions\MissingDependencyException;
use Db2File\Writers\PdfWriter;
use Db2File\Writers\DocxWriter;
use Throwable;

final class ExportManager
{
    public function __construct(
        private readonly QueryBuilder $query
    ) {
    }

    /**
     * Save query results as a CSV file.
     *
     * @param array<string, mixed> $options
     */
    public function saveCsv(
        string $path,
        array $options = []
    ): string {
        $this->validateExportLimit();

        $writer = new CsvWriter();

        $writer->save(
            headings: $this->transformedHeadings(),
            rows: $this->transformedRows(),
            path: $path,
            options: $options
        );

        return $path;
    }

    /**
     * Alias for saveCsv().
     *
     * @param array<string, mixed> $options
     */
    public function toCsv(
        string $path,
        array $options = []
    ): string {
        return $this->saveCsv(
            $path,
            $options
        );
    }

    /**
     * Generate a temporary CSV file and download it.
     *
     * @param array<string, mixed> $options
     */
    public function downloadCsv(
        string $filename = 'export.csv',
        array $options = []
    ): never {
        $this->validateExportLimit();

        if (headers_sent($sourceFile, $sourceLine)) {
            throw new DownloadException(
                sprintf(
                    'Cannot download CSV because HTTP headers were already sent in %s on line %d.',
                    $sourceFile,
                    $sourceLine
                )
            );
        }

        $filename = $this->sanitizeFilename(
            $filename,
            'csv'
        );

        $temporaryPath = tempnam(
            sys_get_temp_dir(),
            'db2file_csv_'
        );

        if ($temporaryPath === false) {
            throw new DownloadException(
                'Unable to create a temporary CSV file.'
            );
        }

        try {
            $writer = new CsvWriter();

            $writer->save(
                headings: $this->transformedHeadings(),
                rows: $this->transformedRows(),
                path: $temporaryPath,
                options: $options
            );

            $fileSize = filesize($temporaryPath);

            if ($fileSize === false) {
                throw new DownloadException(
                    'Unable to determine the generated CSV file size.'
                );
            }

            $this->sendDownloadHeaders(
                filename: $filename,
                contentType: $writer->contentType(),
                contentLength: $fileSize
            );

            $handle = fopen(
                $temporaryPath,
                'rb'
            );

            if ($handle === false) {
                throw new DownloadException(
                    'Unable to open the generated CSV file.'
                );
            }

            try {
                $result = fpassthru($handle);

                if ($result === false) {
                    throw new DownloadException(
                        'Unable to send the generated CSV file.'
                    );
                }
            } finally {
                fclose($handle);
            }
        } catch (DownloadException | FileWriteException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DownloadException(
                'An unexpected error occurred while downloading the CSV file.',
                previous: $exception
            );
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }

        exit;
    }

    /**
     * Stream CSV directly to the browser.
     *
     * @param array<string, mixed> $options
     */
    public function streamCsv(
        string $filename = 'export.csv',
        array $options = []
    ): never {
        $this->validateExportLimit();

        if (headers_sent($sourceFile, $sourceLine)) {
            throw new DownloadException(
                sprintf(
                    'Cannot stream CSV because HTTP headers were already sent in %s on line %d.',
                    $sourceFile,
                    $sourceLine
                )
            );
        }

        $filename = $this->sanitizeFilename(
            $filename,
            'csv'
        );

        $writer = new CsvWriter();

        $this->sendDownloadHeaders(
            filename: $filename,
            contentType: $writer->contentType()
        );

        $output = fopen(
            'php://output',
            'wb'
        );

        if ($output === false) {
            throw new DownloadException(
                'Unable to open the PHP output stream.'
            );
        }

        try {
            $writer->writeToStream(
                headings: $this->transformedHeadings(),
                rows: $this->transformedRows(),
                stream: $output,
                options: $options
            );
        } catch (FileWriteException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DownloadException(
                'An unexpected error occurred while streaming the CSV file.',
                previous: $exception
            );
        } finally {
            fclose($output);
        }

        exit;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function saveXlsx(
        string $path,
        array $options = []
    ): string {
        $this->validateExportLimit();

        $writer = new XlsxWriter();

        $writer->save(
            headings: $this->transformedHeadings(),
            rows: $this->transformedRows(),
            path: $path,
            options: $options
        );

        return $path;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function toXlsx(
        string $path,
        array $options = []
    ): string {
        return $this->saveXlsx(
            $path,
            $options
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public function downloadXlsx(
        string $filename = 'export.xlsx',
        array $options = []
    ): never {
        $this->validateExportLimit();

        if (headers_sent($sourceFile, $sourceLine)) {
            throw new DownloadException(
                sprintf(
                    'Cannot download XLSX because HTTP headers were already sent in %s on line %d.',
                    $sourceFile,
                    $sourceLine
                )
            );
        }

        $filename = $this->sanitizeFilename(
            $filename,
            'xlsx'
        );

        $temporaryPath = tempnam(
            sys_get_temp_dir(),
            'db2file_xlsx_'
        );

        if ($temporaryPath === false) {
            throw new DownloadException(
                'Unable to create a temporary XLSX file.'
            );
        }

        try {
            $writer = new XlsxWriter();

            $writer->save(
                headings: $this->transformedHeadings(),
                rows: $this->transformedRows(),
                path: $temporaryPath,
                options: $options
            );

            $fileSize = filesize($temporaryPath);

            if ($fileSize === false) {
                throw new DownloadException(
                    'Unable to determine the generated XLSX file size.'
                );
            }

            $this->sendDownloadHeaders(
                filename: $filename,
                contentType: $writer->contentType(),
                contentLength: $fileSize
            );

            $handle = fopen(
                $temporaryPath,
                'rb'
            );

            if ($handle === false) {
                throw new DownloadException(
                    'Unable to open the generated XLSX file.'
                );
            }

            try {
                $result = fpassthru($handle);

                if ($result === false) {
                    throw new DownloadException(
                        'Unable to send the generated XLSX file.'
                    );
                }
            } finally {
                fclose($handle);
            }
        } catch (
            DownloadException
            | FileWriteException
            | MissingDependencyException $exception
        ) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DownloadException(
                'An unexpected error occurred while downloading the XLSX file.',
                previous: $exception
            );
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }

        exit;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function savePdf(
        string $path,
        array $options = []
    ): string {
        $this->validateExportLimit();

        $writer = new PdfWriter();

        $writer->save(
            headings: $this->transformedHeadings(),
            rows: $this->transformedRows(),
            path: $path,
            options: $options
        );

        return $path;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function toPdf(
        string $path,
        array $options = []
    ): string {
        return $this->savePdf(
            $path,
            $options
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public function downloadPdf(
        string $filename = 'export.pdf',
        array $options = []
    ): never {
        $this->validateExportLimit();

        if (headers_sent($sourceFile, $sourceLine)) {
            throw new DownloadException(
                sprintf(
                    'Cannot download PDF because HTTP headers were already sent in %s on line %d.',
                    $sourceFile,
                    $sourceLine
                )
            );
        }

        $filename = $this->sanitizeFilename(
            $filename,
            'pdf'
        );

        $temporaryPath = tempnam(
            sys_get_temp_dir(),
            'db2file_pdf_'
        );

        if ($temporaryPath === false) {
            throw new DownloadException(
                'Unable to create a temporary PDF file.'
            );
        }

        try {
            $writer = new PdfWriter();

            $writer->save(
                headings: $this->transformedHeadings(),
                rows: $this->transformedRows(),
                path: $temporaryPath,
                options: $options
            );

            $fileSize = filesize($temporaryPath);

            if ($fileSize === false) {
                throw new DownloadException(
                    'Unable to determine the generated PDF file size.'
                );
            }

            $this->sendDownloadHeaders(
                filename: $filename,
                contentType: $writer->contentType(),
                contentLength: $fileSize
            );

            $handle = fopen(
                $temporaryPath,
                'rb'
            );

            if ($handle === false) {
                throw new DownloadException(
                    'Unable to open the generated PDF file.'
                );
            }

            try {
                $result = fpassthru($handle);

                if ($result === false) {
                    throw new DownloadException(
                        'Unable to send the generated PDF file.'
                    );
                }
            } finally {
                fclose($handle);
            }
        } catch (
            DownloadException
            | FileWriteException
            | MissingDependencyException $exception
        ) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DownloadException(
                'An unexpected error occurred while downloading the PDF file.',
                previous: $exception
            );
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }

        exit;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function saveDocx(
        string $path,
        array $options = []
    ): string {
        $this->validateExportLimit();

        $writer = new DocxWriter();

        $writer->save(
            headings: $this->transformedHeadings(),
            rows: $this->transformedRows(),
            path: $path,
            options: $options
        );

        return $path;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function toDocx(
        string $path,
        array $options = []
    ): string {
        return $this->saveDocx(
            $path,
            $options
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public function downloadDocx(
        string $filename = 'export.docx',
        array $options = []
    ): never {
        $this->validateExportLimit();

        if (headers_sent($sourceFile, $sourceLine)) {
            throw new DownloadException(
                sprintf(
                    'Cannot download DOCX because HTTP headers were already sent in %s on line %d.',
                    $sourceFile,
                    $sourceLine
                )
            );
        }

        $filename = $this->sanitizeFilename(
            $filename,
            'docx'
        );

        $temporaryPath = tempnam(
            sys_get_temp_dir(),
            'db2file_docx_'
        );

        if ($temporaryPath === false) {
            throw new DownloadException(
                'Unable to create a temporary DOCX file.'
            );
        }

        try {
            $writer = new DocxWriter();

            $writer->save(
                headings: $this->transformedHeadings(),
                rows: $this->transformedRows(),
                path: $temporaryPath,
                options: $options
            );

            $fileSize = filesize($temporaryPath);

            if ($fileSize === false) {
                throw new DownloadException(
                    'Unable to determine the generated DOCX file size.'
                );
            }

            $this->sendDownloadHeaders(
                filename: $filename,
                contentType: $writer->contentType(),
                contentLength: $fileSize
            );

            $handle = fopen(
                $temporaryPath,
                'rb'
            );

            if ($handle === false) {
                throw new DownloadException(
                    'Unable to open the generated DOCX file.'
                );
            }

            try {
                $result = fpassthru($handle);

                if ($result === false) {
                    throw new DownloadException(
                        'Unable to send the generated DOCX file.'
                    );
                }
            } finally {
                fclose($handle);
            }
        } catch (
            DownloadException
            | FileWriteException
            | MissingDependencyException $exception
        ) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DownloadException(
                'An unexpected error occurred while downloading the DOCX file.',
                previous: $exception
            );
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }

        exit;
    }

    /**
     * @return array<string, string>
     */
    private function transformedHeadings(): array
    {
        $pipeline = new TransformPipeline(
            $this->query->transformState()
        );

        return $pipeline->headings(
            $this->query->headings()
        );
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function transformedRows(): iterable
    {
        $pipeline = new TransformPipeline(
            $this->query->transformState()
        );

        return $pipeline->rows(
            $this->query->get()
        );
    }

    private function validateExportLimit(): void
    {
        $state = $this->query->state();

        if (
            $state->maximumRows !== null
            && $state->limit === null
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'A limit is required before exporting. The configured maximum is %d rows.',
                    $state->maximumRows
                )
            );
        }

        if (
            $state->maximumRows !== null
            && $state->limit !== null
            && $state->limit > $state->maximumRows
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'The requested limit of %d exceeds the maximum of %d rows.',
                    $state->limit,
                    $state->maximumRows
                )
            );
        }
    }

    private function sanitizeFilename(
        string $filename,
        string $extension
    ): string {
        $filename = trim(
            basename($filename)
        );

        if ($filename === '') {
            $filename = 'export';
        }

        $sanitized = preg_replace(
            '/[^A-Za-z0-9._-]+/',
            '-',
            $filename
        );

        if (
            !is_string($sanitized)
            || $sanitized === ''
        ) {
            $sanitized = 'export';
        }

        $sanitized = trim(
            $sanitized,
            '.-_'
        );

        if ($sanitized === '') {
            $sanitized = 'export';
        }

        $suffix = '.' . strtolower($extension);

        if (
            !str_ends_with(
                strtolower($sanitized),
                $suffix
            )
        ) {
            $sanitized .= $suffix;
        }

        return $sanitized;
    }

    private function sendDownloadHeaders(
        string $filename,
        string $contentType,
        ?int $contentLength = null
    ): void {
        header(
            'Content-Type: ' . $contentType
        );

        $asciiFilename = addcslashes(
            $filename,
            '"\\'
        );

        $utf8Filename = rawurlencode(
            $filename
        );

        /*
         * Use one Content-Disposition header.
         * Sending two causes ERR_RESPONSE_HEADERS_MULTIPLE_CONTENT_DISPOSITION.
         */
        header(
            "Content-Disposition: attachment; filename=\"{$asciiFilename}\"; filename*=UTF-8''{$utf8Filename}"
        );

        if ($contentLength !== null) {
            header(
                'Content-Length: ' . $contentLength
            );
        }

        header(
            'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
        );

        header('Pragma: no-cache');
        header('Expires: 0');
    }
}