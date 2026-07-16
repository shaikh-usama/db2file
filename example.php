<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Db2File\Db2File;

$pdo = new \PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=test;charset=utf8mb4',
    'root',
    'root',
    [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$path = Db2File::make($pdo)
    ->table('orders')
    ->select([
        'id',
        'customer_name',
        'email',
        'total',
        'created_at',
    ])
    ->rename([
        'customer_name' => 'Customer Name',
        'email' => 'Email Address',
        'total' => 'Total Amount',
        'created_at' => 'Order Date',
    ])
    ->hide([
        'id',
    ])
    ->formatDate(
        'created_at',
        'd M Y H:i:s'
    )
    ->limit(100)
    ->export()
    ->saveCsv(__DIR__ . '/orders.csv');

echo "CSV created at: {$path}\n";