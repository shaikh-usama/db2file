# Db2File

Db2File is a framework-independent PHP library for querying MySQL data and
exporting it to CSV, XLSX, DOCX, or PDF. It supports both PDO and mysqli
connections, prepared query bindings, column transformations, file saving,
and browser downloads.

## Requirements

- PHP 8.1 or newer
- MySQL
- PDO MySQL or mysqli
- PHP extensions required by PhpSpreadsheet, PHPWord, and Dompdf

Composer reports any missing required PHP extensions during installation.

## Installation

Install the package with Composer:

```bash
composer require db2file/db2file
```

Load Composer's autoloader in your application:

```php
require __DIR__ . '/vendor/autoload.php';
```

## Connections

### PDO

```php
use Db2File\Db2File;

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=app;charset=utf8mb4',
    'root',
    'password'
);

$query = Db2File::make($pdo);
```

### mysqli

```php
use Db2File\Db2File;

$mysqli = new mysqli(
    '127.0.0.1',
    'root',
    'password',
    'app'
);

$query = Db2File::make($mysqli);
```

The procedural `mysqli_connect()` form is also supported because it returns
a `mysqli` instance.

## Basic Export

```php
use Db2File\Db2File;

$path = Db2File::make($pdo)
    ->table('orders')
    ->select([
        'customer_name',
        'email',
        'total',
        'created_at',
    ])
    ->rename([
        'customer_name' => 'Customer',
        'total' => 'Order Total',
        'created_at' => 'Order Date',
    ])
    ->where('payment_status', '=', 'paid')
    ->currency('total', 'USD')
    ->formatDate('created_at', 'd M Y')
    ->limit(100)
    ->export()
    ->saveCsv(__DIR__ . '/exports/orders.csv');
```

## Export Formats

Save generated files to disk:

```php
$export = Db2File::make($pdo)
    ->table('orders')
    ->select(['id', 'customer_name', 'total'])
    ->limit(1000)
    ->export();

$export->saveCsv(__DIR__ . '/exports/orders.csv');
$export->saveXlsx(__DIR__ . '/exports/orders.xlsx');
$export->saveDocx(__DIR__ . '/exports/orders.docx');
$export->savePdf(__DIR__ . '/exports/orders.pdf');
```

Send a file as an HTTP download:

```php
Db2File::make($pdo)
    ->table('orders')
    ->select(['id', 'customer_name', 'total'])
    ->limit(1000)
    ->export()
    ->downloadXlsx('orders.xlsx');
```

Available download methods are `downloadCsv()`, `downloadXlsx()`,
`downloadDocx()`, and `downloadPdf()`. CSV can also be written directly to
the response with `streamCsv()`.

Download methods send HTTP headers and terminate the current request. Do not
send output before calling them.

## Querying

The fluent query builder supports:

- `where()`, `orWhere()`, and LIKE conditions
- `whereIn()`, `whereNotIn()`, and their OR variants
- `whereBetween()`, `whereNotBetween()`, and their OR variants
- `whereNull()`, `whereNotNull()`, and their OR variants
- `distinct()`, `groupBy()`, and HAVING conditions
- `count()`, `countDistinct()`, `sum()`, `avg()`, `min()`, and `max()`
- `orderBy()`, `latest()`, and `oldest()`
- `limit()`, `offset()`, and `maximumRows()`

Example:

```php
$rows = Db2File::make($pdo)
    ->table('orders')
    ->select(['status'])
    ->count('id', 'Orders', 'order_count')
    ->sum('total', 'Revenue', 'revenue')
    ->whereBetween('created_at', '2026-01-01', '2026-12-31')
    ->groupBy('status')
    ->having('SUM', 'total', '>', 1000)
    ->orderBy('status')
    ->all();
```

Use `get()` for an iterable result, `all()` for an array, or `first()` for one
row. `toSql()` and `bindings()` can be used to inspect the compiled query.

## Transformations

Data can be transformed before it is written:

```php
$export = Db2File::make($pdo)
    ->table('orders')
    ->select(['id', 'customer_name', 'email', 'status', 'total'])
    ->hide(['id'])
    ->capitalize('customer_name')
    ->lowercase('email')
    ->uppercase('status')
    ->currency('total', 'USD')
    ->addColumn(
        'summary',
        'Summary',
        static fn (array $row): string => sprintf(
            '%s: %s',
            $row['customer_name'],
            $row['status']
        )
    )
    ->limit(100)
    ->export();
```

Custom transformations are available through `transformColumn()` and
`transformRow()`. Built-in helpers include `uppercase()`, `lowercase()`,
`capitalize()`, `trimColumn()`, `number()`, `currency()`, `boolean()`, and
`formatDate()`.

## Safety

Db2File validates table, column, and alias identifiers and binds query values
through prepared statements. Always apply a suitable `limit()` or
`maximumRows()` when exporting data from large tables.

## Testing

```bash
composer install
composer test
```

Run only the unit test suite with:

```bash
composer test-unit
```

## License

Db2File is open-source software licensed under the [MIT License](LICENSE).
