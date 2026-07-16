<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Db2File\Db2File;

$pdo = new \PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=test;charset=utf8mb4',
    'root',
    'root',
    [
        \PDO::ATTR_ERRMODE =>
            \PDO::ERRMODE_EXCEPTION,

        \PDO::ATTR_DEFAULT_FETCH_MODE =>
            \PDO::FETCH_ASSOC,

        \PDO::ATTR_EMULATE_PREPARES =>
            false,
    ]
);

$path = Db2File::make($pdo)
    ->table('orders')
    ->select([
        'customer_name',
        'email',
        'status',
        'total',
        'created_at',
    ])
    ->rename([
        'customer_name' => 'Customer',
        'email' => 'Email Address',
        'status' => 'Order Status',
        'total' => 'Order Amount',
        'created_at' => 'Order Date',
    ])
    ->where(
        'payment_status',
        '=',
        'paid'
    )
    ->capitalize('customer_name')
    ->lowercase('email')
    ->uppercase('status')
    ->currency('total', 'USD')
    ->formatDate(
        'created_at',
        'd M Y'
    )
    ->addColumn(
        'summary',
        'Summary',
        static function (array $row): string {
            return sprintf(
                '%s placed a %s order',
                $row['customer_name'],
                $row['status']
            );
        }
    )
    ->limit(100)
    ->export()
    ->saveXlsx(
        __DIR__ . '/exports/orders.xlsx',
        [
            'sheet_name' => 'Paid Orders',
            'title' => 'Paid Orders Report',
            'creator' => 'Db2File',
            'description' => 'Orders exported using Db2File',
            'freeze_header' => true,
            'auto_filter' => true,
            'auto_size' => true,
            'wrap_text' => true,
            'protect_formulas' => true,
        ]
    );

echo "Created: {$path}\n";