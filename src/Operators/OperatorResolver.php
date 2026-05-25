<?php

declare(strict_types=1);

namespace Omoba\LaravelQueryable\Operators;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * Applies a FilterOperator to a query builder for a given column and value.
 *
 * Two modes:
 *  - applyWhere()  → standard WHERE clauses (the common case)
 *  - applyHaving() → HAVING clauses for aggregated/computed columns
 *
 * Both modes treat a value of the string 'null' as a null check.
 */
final class OperatorResolver
{
    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  EloquentBuilder<T>|QueryBuilder  $query
     */
    public static function applyWhere(
        EloquentBuilder|QueryBuilder $query,
        string $column,
        FilterOperator $operator,
        mixed $value,
    ): void {
        if (self::isNullSentinel($value)) {
            $query->whereNull($column);

            return;
        }
        if (self::isNotNullSentinel($value)) {
            $query->whereNotNull($column);

            return;
        }

        match ($operator) {
            FilterOperator::Exact => self::applyExactWhere($query, $column, $value),
            FilterOperator::Like => $query->where($column, 'like', '%'.((string) $value).'%'),
            FilterOperator::In => $query->whereIn($column, self::toList($value)),
            FilterOperator::Between => self::applyRangeWhere($query, $column, $value, castDate: false),
            FilterOperator::DateRange => self::applyRangeWhere($query, $column, $value, castDate: true),
            FilterOperator::Null => $query->whereNull($column),
            FilterOperator::Gt => $query->where($column, '>', $value),
            FilterOperator::Gte => $query->where($column, '>=', $value),
            FilterOperator::Lt => $query->where($column, '<', $value),
            FilterOperator::Lte => $query->where($column, '<=', $value),
        };
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  EloquentBuilder<T>|QueryBuilder  $query
     */
    public static function applyHaving(
        EloquentBuilder|QueryBuilder $query,
        string $column,
        FilterOperator $operator,
        mixed $value,
    ): void {
        if (self::isNullSentinel($value)) {
            self::havingExpression($query, $column, '=', null);

            return;
        }
        if (self::isNotNullSentinel($value)) {
            self::havingExpression($query, $column, '!=', null);

            return;
        }

        match ($operator) {
            FilterOperator::Exact => self::applyExactHaving($query, $column, $value),
            FilterOperator::Like => $query->having($column, 'like', '%'.((string) $value).'%'),
            FilterOperator::In => self::applyInHaving($query, $column, self::toList($value)),
            FilterOperator::Between => self::applyRangeHaving($query, $column, $value, castDate: false),
            FilterOperator::DateRange => self::applyRangeHaving($query, $column, $value, castDate: true),
            FilterOperator::Null => self::havingExpression($query, $column, '=', null),
            FilterOperator::Gt => $query->having($column, '>', $value),
            FilterOperator::Gte => $query->having($column, '>=', $value),
            FilterOperator::Lt => $query->having($column, '<', $value),
            FilterOperator::Lte => $query->having($column, '<=', $value),
        };
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  EloquentBuilder<T>|QueryBuilder  $query
     */
    private static function applyExactWhere(EloquentBuilder|QueryBuilder $query, string $column, mixed $value): void
    {
        $values = self::toList($value);
        if (count($values) === 1) {
            $query->where($column, $values[0]);
        } else {
            $query->whereIn($column, $values);
        }
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  EloquentBuilder<T>|QueryBuilder  $query
     */
    private static function applyExactHaving(EloquentBuilder|QueryBuilder $query, string $column, mixed $value): void
    {
        $values = self::toList($value);
        if (count($values) === 1) {
            $query->having($column, '=', $values[0]);

            return;
        }
        self::applyInHaving($query, $column, $values);
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  EloquentBuilder<T>|QueryBuilder  $query
     * @param  array<int, mixed>  $values
     */
    private static function applyInHaving(EloquentBuilder|QueryBuilder $query, string $column, array $values): void
    {
        if ($values === []) {
            return;
        }
        $query->having(function ($q) use ($column, $values): void {
            foreach ($values as $i => $value) {
                $i === 0
                    ? $q->having($column, '=', $value)
                    : $q->orHaving($column, '=', $value);
            }
        });
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  EloquentBuilder<T>|QueryBuilder  $query
     */
    private static function applyRangeWhere(
        EloquentBuilder|QueryBuilder $query,
        string $column,
        mixed $value,
        bool $castDate,
    ): void {
        [$from, $to] = self::extractFromTo($value, $castDate);
        if ($from !== null && $to !== null) {
            $query->whereBetween($column, [$from, $to]);
        } elseif ($from !== null) {
            $query->where($column, '>=', $from);
        } elseif ($to !== null) {
            $query->where($column, '<=', $to);
        }
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  EloquentBuilder<T>|QueryBuilder  $query
     */
    private static function applyRangeHaving(
        EloquentBuilder|QueryBuilder $query,
        string $column,
        mixed $value,
        bool $castDate,
    ): void {
        [$from, $to] = self::extractFromTo($value, $castDate);
        if ($from !== null && $to !== null) {
            $query->havingBetween($column, ['from' => $from, 'to' => $to]);
        } elseif ($from !== null) {
            $query->having($column, '>=', $from);
        } elseif ($to !== null) {
            $query->having($column, '<=', $to);
        }
    }

    /**
     * @return array{0: mixed, 1: mixed}
     */
    private static function extractFromTo(mixed $value, bool $castDate): array
    {
        if (! is_array($value)) {
            return [null, null];
        }
        $from = $value['from'] ?? null;
        $to = $value['to'] ?? null;

        if ($castDate) {
            $from = self::toDate($from, startOfDay: true);
            $to = self::toDate($to, startOfDay: false);
        }

        return [$from, $to];
    }

    private static function toDate(mixed $value, bool $startOfDay): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof CarbonInterface) {
            return $value;
        }
        $parsed = Carbon::parse((string) $value);

        return $startOfDay ? $parsed->startOfDay() : $parsed->endOfDay();
    }

    /**
     * @return array<int, mixed>
     */
    private static function toList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        if ($value === null) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', (string) $value)),
            static fn (string $v): bool => $v !== '',
        ));
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  EloquentBuilder<T>|QueryBuilder  $query
     */
    private static function havingExpression(
        EloquentBuilder|QueryBuilder $query,
        string $column,
        string $operator,
        mixed $value,
    ): void {
        // Laravel doesn't ship havingNull, so use a raw expression that's still safely parameterised
        // for the value side. Column comes from the model's declared list, not user input.
        if ($value === null) {
            $query->havingRaw("{$column} IS ".($operator === '!=' ? 'NOT ' : '').'NULL');

            return;
        }
        $query->having($column, $operator, $value);
    }

    private static function isNullSentinel(mixed $value): bool
    {
        return $value === 'null';
    }

    private static function isNotNullSentinel(mixed $value): bool
    {
        return $value === 'not_null';
    }
}
