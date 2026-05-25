<?php

declare(strict_types=1);

namespace Omoba\LaravelSearchable\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Omoba\LaravelSearchable\Support\RelationPath;

/**
 * Adds `search` and `searchEncrypted` scopes.
 *
 * Models override `searchable()` and `searchableEncrypted()` to declare which
 * columns (and dot-notated relation columns) the scopes traverse.
 */
trait Searchable
{
    /**
     * Columns (and dot-notated relation columns) used by `scopeSearch`.
     *
     * @return array<int, string>
     */
    public function searchable(): array
    {
        return [];
    }

    /**
     * Columns (and dot-notated relation columns) used by `scopeSearchEncrypted`.
     *
     * @return array<int, string>
     */
    public function searchableEncrypted(): array
    {
        return [];
    }

    /**
     * Hash a plaintext search term before comparing against encrypted columns.
     * Override if you use a different hashing scheme than sha256.
     */
    public function hashSearchTerm(string $term): string
    {
        return hash('sha256', $term);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || $term === '') {
            return $query;
        }
        $fields = $this->searchable();
        if ($fields === []) {
            return $query;
        }
        $like = "%{$term}%";

        return $query->where(function (Builder $q) use ($fields, $like): void {
            foreach ($fields as $field) {
                $path = RelationPath::parse($field);
                if ($path->hasRelation()) {
                    $q->orWhereHas($path->relation, function (Builder $relationQuery) use ($path, $like): void {
                        $relationQuery->where($path->column, 'like', $like);
                    });
                } else {
                    $q->orWhere($path->column, 'like', $like);
                }
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearchEncrypted(Builder $query, ?string $term): Builder
    {
        if ($term === null || $term === '') {
            return $query;
        }
        $fields = $this->searchableEncrypted();
        if ($fields === []) {
            return $query;
        }
        $hashed = $this->hashSearchTerm($term);

        return $query->where(function (Builder $q) use ($fields, $hashed): void {
            foreach ($fields as $field) {
                $path = RelationPath::parse($field);
                if ($path->hasRelation()) {
                    $q->orWhereHas($path->relation, function (Builder $relationQuery) use ($path, $hashed): void {
                        $relationQuery->where($path->column, $hashed);
                    });
                } else {
                    $q->orWhere($path->column, $hashed);
                }
            }
        });
    }
}
