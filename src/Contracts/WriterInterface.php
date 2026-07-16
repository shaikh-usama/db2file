<?php

declare(strict_types=1);

namespace Db2File\Contracts;

interface WriterInterface
{
    /**
     * Return the format name handled by this writer.
     */
    public function format(): string;

    /**
     * Return the file extension without a leading dot.
     */
    public function extension(): string;

    /**
     * Return the HTTP content type for this format.
     */
    public function contentType(): string;

    /**
     * Save exported rows to a file.
     *
     * $headings uses this structure:
     *
     * [
     *     'database_result_key' => 'Export Heading',
     * ]
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
    ): void;
}