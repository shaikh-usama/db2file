<?php

declare(strict_types=1);

namespace Db2File\Tests\Unit;

use Db2File\Transform\ExportTransformState;
use Db2File\Transform\TransformPipeline;
use PHPUnit\Framework\TestCase;

final class TransformPipelineTest extends TestCase
{
    public function testItTransformsAndHidesColumns(): void
    {
        $state = new ExportTransformState(
            hiddenColumns: [
                'first_name',
                'last_name',
            ],
            columnTransformers: [
                'status' => [
                    static fn (mixed $value): string =>
                        strtoupper((string) $value),
                ],
            ],
            virtualColumns: [
                'full_name' => [
                    'heading' => 'Full Name',
                    'callback' => static fn (array $row): string =>
                        $row['first_name']
                        . ' '
                        . $row['last_name'],
                ],
            ]
        );

        $pipeline = new TransformPipeline($state);

        $headings = $pipeline->headings([
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'status' => 'Status',
        ]);

        self::assertSame(
            [
                'status' => 'Status',
                'full_name' => 'Full Name',
            ],
            $headings
        );

        $rows = iterator_to_array(
            $pipeline->rows([
                [
                    'first_name' => 'Ali',
                    'last_name' => 'Khan',
                    'status' => 'active',
                ],
            ]),
            false
        );

        self::assertSame(
            [
                [
                    'status' => 'ACTIVE',
                    'full_name' => 'Ali Khan',
                ],
            ],
            $rows
        );
    }

    public function testItAppliesColumnTransformersInOrder(): void
    {
        $state = new ExportTransformState(
            columnTransformers: [
                'name' => [
                    static fn (mixed $value): string =>
                        trim((string) $value),

                    static fn (mixed $value): string =>
                        strtoupper((string) $value),
                ],
            ]
        );

        $pipeline = new TransformPipeline($state);

        $rows = iterator_to_array(
            $pipeline->rows([
                [
                    'name' => '  Ali Khan  ',
                ],
            ]),
            false
        );

        self::assertSame(
            'ALI KHAN',
            $rows[0]['name']
        );
    }
}