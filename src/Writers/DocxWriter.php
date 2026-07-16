<?php

declare(strict_types=1);

namespace Db2File\Writers;

use Db2File\Contracts\WriterInterface;
use Db2File\Exceptions\FileWriteException;
use Db2File\Exceptions\MissingDependencyException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use Throwable;

final class DocxWriter implements WriterInterface
{
    public function format(): string
    {
        return 'docx';
    }

    public function extension(): string
    {
        return 'docx';
    }

    public function contentType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
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
                'DOCX export requires at least one heading.'
            );
        }

        $this->createDirectory($path);

        try {
            $phpWord = new PhpWord();

            $this->configureDocumentProperties(
                $phpWord,
                $options
            );

            $section = $phpWord->addSection(
                $this->sectionOptions($options)
            );

            $this->writeTitle(
                $section,
                $options
            );

            $table = $section->addTable(
                $this->tableStyle($options)
            );

            $this->writeHeadings(
                $table,
                $headings,
                $options
            );

            $this->writeRows(
                $table,
                $headings,
                $rows,
                $options
            );

            $writer = IOFactory::createWriter(
                $phpWord,
                'Word2007'
            );

            $writer->save($path);
        } catch (FileWriteException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new FileWriteException(
                'Unable to create the DOCX file: '
                . $exception->getMessage(),
                previous: $exception
            );
        }
    }

    private function ensureDependencyExists(): void
    {
        if (!class_exists(PhpWord::class)) {
            throw new MissingDependencyException(
                'DOCX export requires phpoffice/phpword. '
                . 'Install it with: composer require phpoffice/phpword'
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
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function sectionOptions(
        array $options
    ): array {
        $orientation = strtolower(
            (string) ($options['orientation'] ?? 'landscape')
        );

        if (!in_array($orientation, ['portrait', 'landscape'], true)) {
            throw new FileWriteException(
                'DOCX orientation must be portrait or landscape.'
            );
        }

        return [
            'orientation' => $orientation,
            'marginTop' => (int) ($options['margin_top'] ?? 720),
            'marginRight' => (int) ($options['margin_right'] ?? 720),
            'marginBottom' => (int) ($options['margin_bottom'] ?? 720),
            'marginLeft' => (int) ($options['margin_left'] ?? 720),
        ];
    }

    private function writeTitle(
        mixed $section,
        array $options
    ): void {
        $title = trim(
            (string) ($options['title'] ?? 'Database Export')
        );

        if ($title !== '') {
            $section->addText(
                $title,
                [
                    'bold' => true,
                    'size' => (int) ($options['title_size'] ?? 16),
                ],
                [
                    'alignment' => Jc::CENTER,
                    'spaceAfter' => 120,
                ]
            );
        }

        $subtitle = trim(
            (string) ($options['subtitle'] ?? '')
        );

        if ($subtitle !== '') {
            $section->addText(
                $subtitle,
                [
                    'italic' => true,
                    'size' => (int) ($options['subtitle_size'] ?? 10),
                ],
                [
                    'alignment' => Jc::CENTER,
                    'spaceAfter' => 120,
                ]
            );
        }

        if (($options['generated_at'] ?? true) === true) {
            $section->addText(
                'Generated at: ' . date('Y-m-d H:i:s'),
                [
                    'size' => 8,
                ],
                [
                    'alignment' => Jc::RIGHT,
                    'spaceAfter' => 120,
                ]
            );
        }
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function tableStyle(
        array $options
    ): array {
        return [
            'borderSize' => (int) ($options['border_size'] ?? 6),
            'borderColor' => (string) ($options['border_color'] ?? '888888'),
            'cellMargin' => (int) ($options['cell_margin'] ?? 80),
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
        ];
    }

    /**
     * @param array<string, string> $headings
     * @param array<string, mixed> $options
     */
    private function writeHeadings(
        mixed $table,
        array $headings,
        array $options
    ): void {
        $table->addRow(
            (int) ($options['heading_row_height'] ?? 420),
            [
                'tblHeader' => true,
            ]
        );

        foreach ($headings as $heading) {
            $cell = $table->addCell(
                null,
                [
                    'bgColor' => (string) (
                        $options['heading_background'] ?? 'EDEDED'
                    ),
                    'valign' => 'center',
                ]
            );

            $cell->addText(
                $heading,
                [
                    'bold' => true,
                    'size' => (int) ($options['heading_font_size'] ?? 9),
                ],
                [
                    'alignment' => Jc::CENTER,
                ]
            );
        }
    }

    /**
     * @param array<string, string> $headings
     * @param iterable<array<string, mixed>> $rows
     * @param array<string, mixed> $options
     */
    private function writeRows(
        mixed $table,
        array $headings,
        iterable $rows,
        array $options
    ): void {
        $rowNumbers = (bool) (
            $options['row_numbers'] ?? false
        );

        /*
         * Add a row-number heading only when requested.
         *
         * Because the heading row has already been written, this implementation
         * expects row_numbers to be handled by including it in headings later.
         * For now, normal data columns are written only.
         */
        unset($rowNumbers);

        $fontSize = (int) (
            $options['font_size'] ?? 9
        );

        $nullValue = $options['null_value'] ?? '';

        foreach ($rows as $row) {
            $table->addRow();

            foreach (array_keys($headings) as $resultKey) {
                $value = $this->normalizeValue(
                    $row[$resultKey] ?? null,
                    $nullValue
                );

                $cell = $table->addCell(
                    null,
                    [
                        'valign' => 'top',
                    ]
                );

                $cell->addText(
                    $value,
                    [
                        'size' => $fontSize,
                    ],
                    [
                        'spaceAfter' => 0,
                    ]
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function configureDocumentProperties(
        PhpWord $phpWord,
        array $options
    ): void {
        $properties = $phpWord->getDocInfo();

        if (isset($options['creator'])) {
            $properties->setCreator(
                (string) $options['creator']
            );
        }

        if (isset($options['company'])) {
            $properties->setCompany(
                (string) $options['company']
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

        if (isset($options['category'])) {
            $properties->setCategory(
                (string) $options['category']
            );
        }

        if (isset($options['keywords'])) {
            $properties->setKeywords(
                (string) $options['keywords']
            );
        }
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
                'Unable to convert a DOCX value into a string.'
            );
        }

        return $encoded;
    }
}