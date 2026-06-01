<?php

declare(strict_types=1);

namespace Omoba\LaravelQueryable\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Omoba\LaravelQueryable\Exceptions\InvalidFilterField;
use Omoba\LaravelQueryable\Operators\FilterOperator;
use Omoba\LaravelQueryable\Operators\OperatorResolver;
use Omoba\LaravelQueryable\Support\RelationPath;

/**
 * Adds `filter` and `filterHaving` scopes.
 *
 * Models override `filterable()` and `having()` to declare which fields can be
 * filtered and with what operator. Values like 'null' / 'not_null' bypass the
 * declared operator and trigger IS NULL / IS NOT NULL checks.
 */
trait Filterable
{
    /**
     * Fields permitted for filtering.
     *
     * Declare with an explicit operator to pin the field to that operator:
     *   ['name' => 'like', 'created_at' => 'date_range']
     *
     * Declare without an operator to let the package infer from the request value:
     *   ['created_at', 'status', 'amount']
     *   — array with from/to keys → between (use 'date_range' explicitly for Carbon casting)
     *   — anything else          → exact  (CSV strings auto-expand to whereIn)
     *
     * Mixed declarations are valid:
     *   ['amount', 'name' => 'like']
     *
     * @return array<int|string, FilterOperator|string>
     */
    abstract public function filterable(): array;

    /**
     * Map of HAVING-clause filterable field → operator. Used for aggregated or
     * computed columns (e.g. ones produced by `withCount` / `selectRaw`).
     *
     * @return array<string, FilterOperator|string>
     */
    public function having(): array
    {
        return [];
    }

    /**
     * @param  Builder<static>  $query
     * @param  array<string, mixed>|null  $filters
     * @return Builder<static>
     */
    public function scopeFilter(Builder $query, ?array $filters): Builder
    {
        if (empty($filters)) {
            return $query;
        }
        $declared = $this->normalizeDeclared($this->filterable());
        $strict = $this->resolveStrictMode();

        foreach ($filters as $field => $value) {
            if (! array_key_exists($field, $declared)) {
                if ($strict) {
                    throw InvalidFilterField::notDeclared($field, static::class);
                }

                continue;
            }
            if ($value === '' || $value === null) {
                continue;
            }
            $explicitOperator = $declared[$field];
            $operator = $explicitOperator !== null
                ? $this->normalizeOperator($explicitOperator)
                : $this->inferOperator($value);
            $path = RelationPath::parse($field);

            if ($path->hasRelation()) {
                $query->whereHas($path->relation, function (Builder $relationQuery) use ($path, $operator, $value): void {
                    OperatorResolver::applyWhere($relationQuery, $path->column, $operator, $value);
                });
            } else {
                OperatorResolver::applyWhere($query, $path->column, $operator, $value);
            }
        }

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @param  array<string, mixed>|null  $filters
     * @return Builder<static>
     */
    public function scopeFilterHaving(Builder $query, ?array $filters): Builder
    {
        if (empty($filters)) {
            return $query;
        }
        $declared = $this->having();
        $strict = $this->resolveStrictMode();

        foreach ($filters as $field => $value) {
            if (! array_key_exists($field, $declared)) {
                if ($strict) {
                    throw InvalidFilterField::notDeclaredForHaving($field, static::class);
                }

                continue;
            }
            if ($value === '' || $value === null) {
                continue;
            }
            $operator = $this->normalizeOperator($declared[$field]);
            OperatorResolver::applyHaving($query, $field, $operator, $value);
        }

        return $query;
    }

    /**
     * @param  array<int|string, FilterOperator|string>  $declared
     * @return array<string, FilterOperator|string|null>
     */
    private function normalizeDeclared(array $declared): array
    {
        $normalized = [];
        foreach ($declared as $key => $value) {
            if (is_int($key)) {
                $normalized[(string) $value] = null;
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function inferOperator(mixed $value): FilterOperator
    {
        if (is_array($value) && (array_key_exists('from', $value) || array_key_exists('to', $value))) {
            return FilterOperator::Between;
        }

        return FilterOperator::Exact;
    }

    private function normalizeOperator(FilterOperator|string $operator): FilterOperator
    {
        return $operator instanceof FilterOperator
            ? $operator
            : FilterOperator::fromString($operator);
    }

    private function resolveStrictMode(): bool
    {
        if (! function_exists('config')) {
            return true;
        }

        return (bool) config('queryable.strict', true);
    }
}
