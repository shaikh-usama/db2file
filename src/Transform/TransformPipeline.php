<?php

declare(strict_types=1);

namespace Db2File\Transform;

use Db2File\Exceptions\TransformException;
use Throwable;

final class TransformPipeline
{
    public function __construct(
        private readonly ExportTransformState $state
    ) {
    }

    /**
     * Apply hidden and virtual columns to export headings.
     *
     * @param array<string, string> $headings
     *
     * @return array<string, string>
     */
    public function headings(array $headings): array
    {
        foreach ($this->state->hiddenColumns as $column) {
            unset($headings[$column]);
        }

        foreach ($this->state->virtualColumns as $key => $definition) {
            $headings[$key] = $definition['heading'];
        }

        return $headings;
    }

    /**
     * Transform rows lazily.
     *
     * @param iterable<array<string, mixed>> $rows
     *
     * @return iterable<array<string, mixed>>
     */
    public function rows(iterable $rows): iterable
    {
        foreach ($rows as $rowIndex => $row) {
            try {
                $row = $this->applyColumnTransformers($row);
                $row = $this->applyRowTransformers($row);
                $row = $this->addVirtualColumns($row);
                $row = $this->removeHiddenColumns($row);

                yield $row;
            } catch (TransformException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                throw new TransformException(
                    sprintf(
                        'Unable to transform export row %s.',
                        (string) $rowIndex
                    ),
                    previous: $exception
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function applyColumnTransformers(
        array $row
    ): array {
        foreach (
            $this->state->columnTransformers
            as $column => $transformers
        ) {
            if (!array_key_exists($column, $row)) {
                continue;
            }

            foreach ($transformers as $transformer) {
                $row[$column] = $transformer(
                    $row[$column],
                    $row,
                    $column
                );
            }
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function applyRowTransformers(
        array $row
    ): array {
        foreach ($this->state->rowTransformers as $transformer) {
            $transformed = $transformer($row);

            if (!is_array($transformed)) {
                throw new TransformException(
                    'A row transformer must return an array.'
                );
            }

            $row = $transformed;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function addVirtualColumns(
        array $row
    ): array {
        foreach (
            $this->state->virtualColumns
            as $key => $definition
        ) {
            $row[$key] = $definition['callback'](
                $row,
                $key
            );
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function removeHiddenColumns(
        array $row
    ): array {
        foreach ($this->state->hiddenColumns as $column) {
            unset($row[$column]);
        }

        return $row;
    }
}