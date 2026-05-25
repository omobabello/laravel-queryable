<?php

declare(strict_types=1);

namespace Omoba\LaravelQueryable\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Omoba\LaravelQueryable\Exceptions\InvalidSortField;
use Omoba\LaravelQueryable\Support\SortSpec;

/**
 * Adds the `sort` scope.
 *
 * Accepts a string like '-created_at,name' or an associative array like
 * ['created_at' => 'desc', 'name' => 'asc']. Multi-column sort is preserved
 * in the order given.
 *
 * Relation sorting (e.g. 'company.name') is intentionally not supported in v1
 * — it requires joins and risks duplicate rows / column collisions.
 */
trait Sortable
{
    /**
     * Fields permitted in `scopeSort`.
     *
     * @return array<int, string>
     */
    public function sortable(): array
    {
        return [];
    }

    /**
     * @param  Builder<static>  $query
     * @param  string|array<int|string, mixed>|null  $spec
     * @return Builder<static>
     */
    public function scopeSort(Builder $query, string|array|null $spec): Builder
    {
        $pairs = SortSpec::parse($spec);
        if ($pairs === []) {
            return $query;
        }
        $allowed = array_flip($this->sortable());
        $strict = $this->resolveSortableStrictMode();

        foreach ($pairs as [$field, $direction]) {
            if (! isset($allowed[$field])) {
                if ($strict) {
                    throw InvalidSortField::notDeclared($field, static::class);
                }

                continue;
            }
            $query->orderBy($field, $direction);
        }

        return $query;
    }

    private function resolveSortableStrictMode(): bool
    {
        if (! function_exists('config')) {
            return true;
        }

        return (bool) config('queryable.strict', true);
    }
}
