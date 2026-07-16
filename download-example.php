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

Db2File::make($pdo)
    ->table('orders')
    ->select([
        'id',
        'customer_name',
        'email',
        'total',
        'created_at',
    ])
    ->rename([
        'id' => 'Order ID',
        'customer_name' => 'Customer',
        'email' => 'Email Address',
        'total' => 'Order Total',
        'created_at' => 'Order Date',
    ])
    ->where('payment_status', '=', 'paid')
    ->orderBy('created_at', 'DESC')
    ->limit(100)
    ->export()
    ->downloadCsv('paid-orders.csv');