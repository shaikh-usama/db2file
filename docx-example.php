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
    ->limit(100)
    ->export()
    ->saveDocx(
        __DIR__ . '/exports/orders.docx',
        [
            'title' => 'Paid Orders Report',
            'subtitle' => 'All successfully paid orders',
            'orientation' => 'landscape',
            'creator' => 'Db2File',
            'company' => 'Your Company',
            'subject' => 'Paid Orders',
            'description' => 'Generated using Db2File',
            'generated_at' => true,
            'font_size' => 9,
            'heading_font_size' => 9,
            'heading_background' => 'EDEDED',
        ]
    );

echo "Created: {$path}\n";