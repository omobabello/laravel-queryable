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
     * Map of filterable field → operator.
     *
     * Field may use dot notation (`company.name`) for relations.
     * Operator may be a {@see FilterOperator} value or its string equivalent.
     *
     * @return array<string, FilterOperator|string>
     */
    public function filterable(): array
    {
        return [];
    }

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
        $declared = $this->filterable();
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
            $operator = $this->normalizeOperator($declared[$field]);
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
