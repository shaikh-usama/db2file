<?php

declare(strict_types=1);

namespace Db2File\Query;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Db2File\Database\MySqlCompiler;
use Db2File\Database\MySqlExecutor;
use Db2File\Database\MySqliExecutor;
use Db2File\Database\MySqlIdentifierQuoter;
use Db2File\Exceptions\InvalidArgumentException;
use Db2File\Exceptions\TransformException;
use Db2File\Export\ExportManager;
use Db2File\Transform\ExportTransformState;
use PDO;
use mysqli;
use Throwable;

final class QueryBuilder
{
    private const OPERATORS = [
        '=',
        '!=',
        '<>',
        '>',
        '>=',
        '<',
        '<=',
        'LIKE',
        'NOT LIKE',
    ];

    public function __construct(
        private readonly PDO|mysqli $connection,
        private readonly IdentifierValidator $validator,
        private readonly QueryState $state = new QueryState(),
        private readonly ExportTransformState $transformState = new ExportTransformState()
    ) {
    }

    public function table(string $table): self
    {
        $this->validator->validate($table);

        return $this->copy(table: $table);
    }

    /**
     * @param array<int, string> $columns
     */
    public function select(array $columns): self
    {
        if ($columns === []) {
            throw new InvalidArgumentException(
                'At least one column must be selected.'
            );
        }

        foreach ($columns as $column) {
            if (!is_string($column) || trim($column) === '') {
                throw new InvalidArgumentException(
                    'Every selected column must be a non-empty string.'
                );
            }

            $this->validator->validate($column);
        }

        return $this->copy(
            selectedColumns: array_values(array_unique($columns))
        );
    }

    /**
     * @param array<string, string> $columns
     */
    public function rename(array $columns): self
    {
        foreach ($columns as $column => $heading) {
            $this->validator->validate($column);

            if (!is_string($heading) || trim($heading) === '') {
                throw new InvalidArgumentException(
                    "The heading for '{$column}' cannot be empty."
                );
            }
        }

        return $this->copy(
            renamedColumns: array_merge(
                $this->state->renamedColumns,
                $columns
            )
        );
    }

    public function distinct(bool $enabled = true): self
    {
        return $this->copy(distinct: $enabled);
    }

    public function where(
        string $column,
        string $operator,
        mixed $value = null
    ): self {
        return $this->addCondition(
            'AND',
            $column,
            $operator,
            $value
        );
    }

    public function orWhere(
        string $column,
        string $operator,
        mixed $value = null
    ): self {
        return $this->addCondition(
            'OR',
            $column,
            $operator,
            $value
        );
    }

    public function whereLike(
        string $column,
        string $pattern
    ): self {
        return $this->where($column, 'LIKE', $pattern);
    }

    public function orWhereLike(
        string $column,
        string $pattern
    ): self {
        return $this->orWhere($column, 'LIKE', $pattern);
    }

    public function whereNotLike(
        string $column,
        string $pattern
    ): self {
        return $this->where($column, 'NOT LIKE', $pattern);
    }

    public function orWhereNotLike(
        string $column,
        string $pattern
    ): self {
        return $this->orWhere($column, 'NOT LIKE', $pattern);
    }

    /**
     * @param array<int, mixed> $values
     */
    public function whereIn(
        string $column,
        array $values
    ): self {
        return $this->addListCondition(
            'AND',
            $column,
            $values,
            false
        );
    }

    /**
     * @param array<int, mixed> $values
     */
    public function orWhereIn(
        string $column,
        array $values
    ): self {
        return $this->addListCondition(
            'OR',
            $column,
            $values,
            false
        );
    }

    /**
     * @param array<int, mixed> $values
     */
    public function whereNotIn(
        string $column,
        array $values
    ): self {
        return $this->addListCondition(
            'AND',
            $column,
            $values,
            true
        );
    }

    /**
     * @param array<int, mixed> $values
     */
    public function orWhereNotIn(
        string $column,
        array $values
    ): self {
        return $this->addListCondition(
            'OR',
            $column,
            $values,
            true
        );
    }

    public function whereBetween(
        string $column,
        mixed $from,
        mixed $to
    ): self {
        return $this->addBetweenCondition(
            'AND',
            $column,
            $from,
            $to,
            false
        );
    }

    public function orWhereBetween(
        string $column,
        mixed $from,
        mixed $to
    ): self {
        return $this->addBetweenCondition(
            'OR',
            $column,
            $from,
            $to,
            false
        );
    }

    public function whereNotBetween(
        string $column,
        mixed $from,
        mixed $to
    ): self {
        return $this->addBetweenCondition(
            'AND',
            $column,
            $from,
            $to,
            true
        );
    }

    public function orWhereNotBetween(
        string $column,
        mixed $from,
        mixed $to
    ): self {
        return $this->addBetweenCondition(
            'OR',
            $column,
            $from,
            $to,
            true
        );
    }

    public function whereNull(string $column): self
    {
        return $this->addNullCondition(
            'AND',
            $column,
            false
        );
    }

    public function orWhereNull(string $column): self
    {
        return $this->addNullCondition(
            'OR',
            $column,
            false
        );
    }

    public function whereNotNull(string $column): self
    {
        return $this->addNullCondition(
            'AND',
            $column,
            true
        );
    }

    public function orWhereNotNull(string $column): self
    {
        return $this->addNullCondition(
            'OR',
            $column,
            true
        );
    }

    public function groupBy(string ...$columns): self
    {
        if ($columns === []) {
            throw new InvalidArgumentException(
                'At least one GROUP BY column is required.'
            );
        }

        foreach ($columns as $column) {
            $this->validator->validate($column);
        }

        return $this->copy(
            groupBy: array_values(
                array_unique([
                    ...$this->state->groupBy,
                    ...$columns,
                ])
            )
        );
    }

    public function count(
        string $column = '*',
        string $heading = 'Count',
        ?string $alias = null
    ): self {
        return $this->addAggregate(
            AggregateFunction::Count,
            $column,
            $heading,
            $alias
        );
    }

    public function countDistinct(
        string $column,
        string $heading = 'Count',
        ?string $alias = null
    ): self {
        return $this->addAggregate(
            AggregateFunction::Count,
            $column,
            $heading,
            $alias,
            true
        );
    }

    public function sum(
        string $column,
        string $heading = 'Sum',
        ?string $alias = null
    ): self {
        return $this->addAggregate(
            AggregateFunction::Sum,
            $column,
            $heading,
            $alias
        );
    }

    public function avg(
        string $column,
        string $heading = 'Average',
        ?string $alias = null
    ): self {
        return $this->addAggregate(
            AggregateFunction::Average,
            $column,
            $heading,
            $alias
        );
    }

    public function min(
        string $column,
        string $heading = 'Minimum',
        ?string $alias = null
    ): self {
        return $this->addAggregate(
            AggregateFunction::Minimum,
            $column,
            $heading,
            $alias
        );
    }

    public function max(
        string $column,
        string $heading = 'Maximum',
        ?string $alias = null
    ): self {
        return $this->addAggregate(
            AggregateFunction::Maximum,
            $column,
            $heading,
            $alias
        );
    }

    public function having(
        string $function,
        string $column,
        string $operator,
        mixed $value
    ): self {
        return $this->addHaving(
            'AND',
            $function,
            $column,
            $operator,
            $value
        );
    }

    public function orHaving(
        string $function,
        string $column,
        string $operator,
        mixed $value
    ): self {
        return $this->addHaving(
            'OR',
            $function,
            $column,
            $operator,
            $value
        );
    }

    public function orderBy(
        string $column,
        string $direction = 'ASC'
    ): self {
        $this->validator->validate($column);

        $direction = strtoupper(trim($direction));

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException(
                'Order direction must be ASC or DESC.'
            );
        }

        return $this->copy(
            orders: [
                ...$this->state->orders,
                new Order($column, $direction),
            ]
        );
    }

    public function latest(
        string $column = 'created_at'
    ): self {
        return $this->orderBy($column, 'DESC');
    }

    public function oldest(
        string $column = 'created_at'
    ): self {
        return $this->orderBy($column, 'ASC');
    }

    public function limit(int $limit): self
    {
        if ($limit < 1) {
            throw new InvalidArgumentException(
                'The export limit must be greater than zero.'
            );
        }

        if (
            $this->state->maximumRows !== null
            && $limit > $this->state->maximumRows
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'The requested limit of %d exceeds the maximum of %d rows.',
                    $limit,
                    $this->state->maximumRows
                )
            );
        }

        return $this->copy(limit: $limit);
    }

    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new InvalidArgumentException(
                'The export offset cannot be negative.'
            );
        }

        return $this->copy(offset: $offset);
    }

    public function maximumRows(int $maximumRows): self
    {
        if ($maximumRows < 1) {
            throw new InvalidArgumentException(
                'Maximum rows must be greater than zero.'
            );
        }

        if (
            $this->state->limit !== null
            && $this->state->limit > $maximumRows
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'The current limit of %d exceeds the new maximum of %d rows.',
                    $this->state->limit,
                    $maximumRows
                )
            );
        }

        return $this->copy(maximumRows: $maximumRows);
    }

    public function transformColumn(
        string $column,
        callable $callback
    ): self {
        $resultKey = $this->normalizeResultKey($column);

        $transformers = $this->transformState->columnTransformers;
        $transformers[$resultKey] ??= [];
        $transformers[$resultKey][] = Closure::fromCallable($callback);

        return $this->replaceTransformState(
            columnTransformers: $transformers
        );
    }

    public function transformRow(
        callable $callback
    ): self {
        return $this->replaceTransformState(
            rowTransformers: [
                ...$this->transformState->rowTransformers,
                Closure::fromCallable($callback),
            ]
        );
    }

    /**
     * @param array<int, string> $columns
     */
    public function hide(array $columns): self
    {
        if ($columns === []) {
            return $this;
        }

        $hidden = $this->transformState->hiddenColumns;

        foreach ($columns as $column) {
            if (!is_string($column) || trim($column) === '') {
                throw new InvalidArgumentException(
                    'Every hidden column must be a non-empty string.'
                );
            }

            $hidden[] = $this->normalizeResultKey($column);
        }

        return $this->replaceTransformState(
            hiddenColumns: array_values(array_unique($hidden))
        );
    }

    public function addColumn(
        string $key,
        string $heading,
        callable $callback
    ): self {
        $key = trim($key);
        $heading = trim($heading);

        if ($key === '') {
            throw new InvalidArgumentException(
                'A virtual column key cannot be empty.'
            );
        }

        if ($heading === '') {
            throw new InvalidArgumentException(
                'A virtual column heading cannot be empty.'
            );
        }

        $this->validator->validateAlias($key);

        if (array_key_exists($key, $this->headings())) {
            throw new InvalidArgumentException(
                "The virtual column key '{$key}' conflicts with an existing column."
            );
        }

        $virtualColumns = $this->transformState->virtualColumns;

        if (array_key_exists($key, $virtualColumns)) {
            throw new InvalidArgumentException(
                "The virtual column '{$key}' already exists."
            );
        }

        $virtualColumns[$key] = [
            'heading' => $heading,
            'callback' => Closure::fromCallable($callback),
        ];

        return $this->replaceTransformState(
            virtualColumns: $virtualColumns
        );
    }

    public function uppercase(string $column): self
    {
        return $this->transformColumn(
            $column,
            static function (mixed $value): mixed {
                if ($value === null) {
                    return null;
                }

                return function_exists('mb_strtoupper')
                    ? mb_strtoupper((string) $value, 'UTF-8')
                    : strtoupper((string) $value);
            }
        );
    }

    public function lowercase(string $column): self
    {
        return $this->transformColumn(
            $column,
            static function (mixed $value): mixed {
                if ($value === null) {
                    return null;
                }

                return function_exists('mb_strtolower')
                    ? mb_strtolower((string) $value, 'UTF-8')
                    : strtolower((string) $value);
            }
        );
    }

    public function capitalize(string $column): self
    {
        return $this->transformColumn(
            $column,
            static function (mixed $value): mixed {
                if ($value === null) {
                    return null;
                }

                $string = (string) $value;

                return function_exists('mb_convert_case')
                    ? mb_convert_case($string, MB_CASE_TITLE, 'UTF-8')
                    : ucwords(strtolower($string));
            }
        );
    }

    public function trimColumn(string $column): self
    {
        return $this->transformColumn(
            $column,
            static fn (mixed $value): mixed =>
                is_string($value) ? trim($value) : $value
        );
    }

    public function number(
        string $column,
        int $decimals = 2,
        string $decimalSeparator = '.',
        string $thousandsSeparator = ','
    ): self {
        if ($decimals < 0) {
            throw new InvalidArgumentException(
                'Number decimals cannot be negative.'
            );
        }

        return $this->transformColumn(
            $column,
            static function (mixed $value) use (
                $decimals,
                $decimalSeparator,
                $thousandsSeparator
            ): mixed {
                if ($value === null || $value === '') {
                    return $value;
                }

                if (!is_numeric($value)) {
                    throw new TransformException(
                        'The value cannot be formatted as a number.'
                    );
                }

                return number_format(
                    (float) $value,
                    $decimals,
                    $decimalSeparator,
                    $thousandsSeparator
                );
            }
        );
    }

    public function currency(
        string $column,
        string $currency = 'USD',
        int $decimals = 2,
        ?string $symbol = null,
        bool $symbolAfter = false
    ): self {
        $currency = strtoupper(trim($currency));

        if ($currency === '') {
            throw new InvalidArgumentException(
                'Currency code cannot be empty.'
            );
        }

        if ($decimals < 0) {
            throw new InvalidArgumentException(
                'Currency decimals cannot be negative.'
            );
        }

        $symbol ??= $this->currencySymbol($currency);

        return $this->transformColumn(
            $column,
            static function (mixed $value) use (
                $currency,
                $decimals,
                $symbol,
                $symbolAfter
            ): mixed {
                if ($value === null || $value === '') {
                    return $value;
                }

                if (!is_numeric($value)) {
                    throw new TransformException(
                        "The value cannot be formatted as {$currency} currency."
                    );
                }

                $formatted = number_format(
                    (float) $value,
                    $decimals,
                    '.',
                    ','
                );

                return $symbolAfter
                    ? "{$formatted} {$symbol}"
                    : "{$symbol}{$formatted}";
            }
        );
    }

    public function boolean(
        string $column,
        string $trueValue = 'Yes',
        string $falseValue = 'No',
        string $nullValue = ''
    ): self {
        return $this->transformColumn(
            $column,
            static function (mixed $value) use (
                $trueValue,
                $falseValue,
                $nullValue
            ): string {
                if ($value === null) {
                    return $nullValue;
                }

                $boolean = filter_var(
                    $value,
                    FILTER_VALIDATE_BOOL,
                    FILTER_NULL_ON_FAILURE
                );

                if ($boolean === null) {
                    $boolean = (bool) $value;
                }

                return $boolean ? $trueValue : $falseValue;
            }
        );
    }

    public function formatDate(
        string $column,
        string $format = 'Y-m-d',
        ?string $inputFormat = null
    ): self {
        if (trim($format) === '') {
            throw new InvalidArgumentException(
                'Date output format cannot be empty.'
            );
        }

        return $this->transformColumn(
            $column,
            static function (mixed $value) use (
                $format,
                $inputFormat
            ): mixed {
                if ($value === null || $value === '') {
                    return $value;
                }

                if ($value instanceof DateTimeInterface) {
                    return $value->format($format);
                }

                if (!is_string($value)) {
                    throw new TransformException(
                        'Date values must be strings or DateTimeInterface objects.'
                    );
                }

                if ($inputFormat !== null) {
                    $date = DateTimeImmutable::createFromFormat(
                        $inputFormat,
                        $value
                    );

                    $errors = DateTimeImmutable::getLastErrors();

                    if (
                        $date === false
                        || (
                            is_array($errors)
                            && (
                                $errors['warning_count'] > 0
                                || $errors['error_count'] > 0
                            )
                        )
                    ) {
                        throw new TransformException(
                            sprintf(
                                'Date value "%s" does not match input format "%s".',
                                $value,
                                $inputFormat
                            )
                        );
                    }

                    return $date->format($format);
                }

                try {
                    return (new DateTimeImmutable($value))
                        ->format($format);
                } catch (Throwable $exception) {
                    throw new TransformException(
                        sprintf(
                            'Unable to parse date value "%s".',
                            $value
                        ),
                        previous: $exception
                    );
                }
            }
        );
    }

    public function compile(): CompiledQuery
    {
        $compiler = new MySqlCompiler(
            new MySqlIdentifierQuoter($this->validator)
        );

        return $compiler->compile($this->state);
    }

    public function toSql(): string
    {
        return $this->compile()->sql;
    }

    /**
     * @return array<string, mixed>
     */
    public function bindings(): array
    {
        return $this->compile()->bindings;
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    public function get(): iterable
    {
        $executor = $this->connection instanceof PDO
            ? new MySqlExecutor($this->connection)
            : new MySqliExecutor($this->connection);

        return $executor->execute($this->compile());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return iterator_to_array($this->get(), false);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        foreach ($this->limit(1)->get() as $row) {
            return $row;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public function headings(): array
    {
        $headings = [];

        foreach ($this->state->selectedColumns as $column) {
            $resultKey = $this->normalizeResultKey($column);

            $headings[$resultKey] =
                $this->state->renamedColumns[$column]
                ?? $column;
        }

        foreach ($this->state->aggregates as $aggregate) {
            $headings[$aggregate->alias] = $aggregate->heading;
        }

        return $headings;
    }

    public function export(): ExportManager
    {
        return new ExportManager($this);
    }

    public function state(): QueryState
    {
        return $this->state;
    }

    public function transformState(): ExportTransformState
    {
        return $this->transformState;
    }

    public function connection(): PDO|mysqli
    {
        return $this->connection;
    }

    private function addCondition(
        string $boolean,
        string $column,
        string $operator,
        mixed $value
    ): self {
        $this->validator->validate($column);

        $operator = strtoupper(trim($operator));

        if (!in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException(
                "Unsupported WHERE operator: {$operator}"
            );
        }

        return $this->copy(
            conditions: [
                ...$this->state->conditions,
                new Condition(
                    $boolean,
                    $column,
                    $operator,
                    $value
                ),
            ]
        );
    }

    /**
     * @param array<int, mixed> $values
     */
    private function addListCondition(
        string $boolean,
        string $column,
        array $values,
        bool $negated
    ): self {
        $this->validator->validate($column);

        if ($values === []) {
            throw new InvalidArgumentException(
                'An IN condition requires at least one value.'
            );
        }

        return $this->copy(
            conditions: [
                ...$this->state->conditions,
                new ListCondition(
                    $boolean,
                    $column,
                    array_values($values),
                    $negated
                ),
            ]
        );
    }

    private function addBetweenCondition(
        string $boolean,
        string $column,
        mixed $from,
        mixed $to,
        bool $negated
    ): self {
        $this->validator->validate($column);

        return $this->copy(
            conditions: [
                ...$this->state->conditions,
                new BetweenCondition(
                    $boolean,
                    $column,
                    $from,
                    $to,
                    $negated
                ),
            ]
        );
    }

    private function addNullCondition(
        string $boolean,
        string $column,
        bool $negated
    ): self {
        $this->validator->validate($column);

        return $this->copy(
            conditions: [
                ...$this->state->conditions,
                new NullCondition(
                    $boolean,
                    $column,
                    $negated
                ),
            ]
        );
    }

    private function addAggregate(
        AggregateFunction $function,
        string $column,
        string $heading,
        ?string $alias,
        bool $distinct = false
    ): self {
        if ($column !== '*') {
            $this->validator->validate($column);
        }

        if (
            $column === '*'
            && $function !== AggregateFunction::Count
        ) {
            throw new InvalidArgumentException(
                'Only COUNT can use * as its column.'
            );
        }

        if ($distinct && $column === '*') {
            throw new InvalidArgumentException(
                'COUNT DISTINCT cannot use * as its column.'
            );
        }

        if (trim($heading) === '') {
            throw new InvalidArgumentException(
                'An aggregate heading cannot be empty.'
            );
        }

        $alias ??= $this->createAggregateAlias(
            $function,
            $column,
            $distinct
        );

        $this->validator->validateAlias($alias);

        foreach ($this->state->aggregates as $aggregate) {
            if ($aggregate->alias === $alias) {
                throw new InvalidArgumentException(
                    "The aggregate alias '{$alias}' is already in use."
                );
            }
        }

        return $this->copy(
            aggregates: [
                ...$this->state->aggregates,
                new Aggregate(
                    $function,
                    $column,
                    $alias,
                    $heading,
                    $distinct
                ),
            ]
        );
    }

    private function addHaving(
        string $boolean,
        string $function,
        string $column,
        string $operator,
        mixed $value
    ): self {
        $aggregateFunction = AggregateFunction::fromString(
            $function
        );

        if ($column !== '*') {
            $this->validator->validate($column);
        }

        if (
            $column === '*'
            && $aggregateFunction !== AggregateFunction::Count
        ) {
            throw new InvalidArgumentException(
                'Only COUNT can use * inside a HAVING condition.'
            );
        }

        $operator = strtoupper(trim($operator));

        if (!in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException(
                "Unsupported HAVING operator: {$operator}"
            );
        }

        return $this->copy(
            having: [
                ...$this->state->having,
                new HavingCondition(
                    $boolean,
                    $aggregateFunction,
                    $column,
                    $operator,
                    $value
                ),
            ]
        );
    }

    private function createAggregateAlias(
        AggregateFunction $function,
        string $column,
        bool $distinct
    ): string {
        $normalizedColumn = $column === '*'
            ? 'all'
            : str_replace('.', '_', $column);

        $prefix = strtolower($function->value);

        if ($distinct) {
            $prefix .= '_distinct';
        }

        return "{$prefix}_{$normalizedColumn}";
    }

    private function normalizeResultKey(string $column): string
    {
        if (!str_contains($column, '.')) {
            return $column;
        }

        $parts = explode('.', $column);

        return (string) end($parts);
    }

    private function currencySymbol(string $currency): string
    {
        return match ($currency) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY', 'CNY' => '¥',
            'INR' => '₹',
            'PKR' => 'Rs. ',
            'AED' => 'AED ',
            'SAR' => 'SAR ',
            'CAD' => 'CA$',
            'AUD' => 'A$',
            default => $currency . ' ',
        };
    }

    /**
     * @param array<int, string>|null $hiddenColumns
     * @param array<string, array<int, Closure>>|null $columnTransformers
     * @param array<int, Closure>|null $rowTransformers
     * @param array<string, array{heading: string, callback: Closure}>|null $virtualColumns
     */
    private function replaceTransformState(
        ?array $hiddenColumns = null,
        ?array $columnTransformers = null,
        ?array $rowTransformers = null,
        ?array $virtualColumns = null
    ): self {
        return new self(
            $this->connection,
            $this->validator,
            $this->state,
            new ExportTransformState(
                hiddenColumns: $hiddenColumns
                    ?? $this->transformState->hiddenColumns,
                columnTransformers: $columnTransformers
                    ?? $this->transformState->columnTransformers,
                rowTransformers: $rowTransformers
                    ?? $this->transformState->rowTransformers,
                virtualColumns: $virtualColumns
                    ?? $this->transformState->virtualColumns
            )
        );
    }

    /**
     * @param array<int, string>|null $selectedColumns
     * @param array<string, string>|null $renamedColumns
     * @param array<int, Condition|BetweenCondition|ListCondition|NullCondition>|null $conditions
     * @param array<int, Aggregate>|null $aggregates
     * @param array<int, string>|null $groupBy
     * @param array<int, HavingCondition>|null $having
     * @param array<int, Order>|null $orders
     */
    private function copy(
        ?string $table = null,
        ?array $selectedColumns = null,
        ?array $renamedColumns = null,
        ?array $conditions = null,
        ?array $aggregates = null,
        ?array $groupBy = null,
        ?array $having = null,
        ?array $orders = null,
        ?bool $distinct = null,
        ?int $limit = null,
        ?int $offset = null,
        ?int $maximumRows = null
    ): self {
        $newState = new QueryState(
            table: $table ?? $this->state->table,
            selectedColumns: $selectedColumns
                ?? $this->state->selectedColumns,
            renamedColumns: $renamedColumns
                ?? $this->state->renamedColumns,
            conditions: $conditions
                ?? $this->state->conditions,
            aggregates: $aggregates
                ?? $this->state->aggregates,
            groupBy: $groupBy
                ?? $this->state->groupBy,
            having: $having
                ?? $this->state->having,
            orders: $orders
                ?? $this->state->orders,
            distinct: $distinct
                ?? $this->state->distinct,
            limit: $limit
                ?? $this->state->limit,
            offset: $offset
                ?? $this->state->offset,
            maximumRows: $maximumRows
                ?? $this->state->maximumRows
        );

        return new self(
            $this->connection,
            $this->validator,
            $newState,
            $this->transformState
        );
    }
}
