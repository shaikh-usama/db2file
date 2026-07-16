<?php

declare(strict_types=1);

namespace Db2File\Transform;

use Closure;

final readonly class ExportTransformState
{
    /**
     * @param array<int, string> $hiddenColumns
     * @param array<string, array<int, Closure>> $columnTransformers
     * @param array<int, Closure> $rowTransformers
     * @param array<string, array{
     *     heading: string,
     *     callback: Closure
     * }> $virtualColumns
     */
    public function __construct(
        public array $hiddenColumns = [],
        public array $columnTransformers = [],
        public array $rowTransformers = [],
        public array $virtualColumns = []
    ) {
    }
}