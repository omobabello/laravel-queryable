<?php

declare(strict_types=1);

namespace Omoba\LaravelQueryable\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class DatabaseDriver
{
    /**
     * Return the right LIKE operator for the connection's driver:
     * `ilike` on PostgreSQL (case-insensitive), `like` everywhere else
     * (MySQL/MariaDB are case-insensitive by default collation; SQLite is
     * case-insensitive for ASCII).
     *
     * Falls back to `like` for non-Laravel-core connection types that don't
     * expose `getDriverName()`.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  EloquentBuilder<T>|QueryBuilder  $query
     */
    public static function likeOperator(EloquentBuilder|QueryBuilder $query): string
    {
        $connection = $query->getConnection();
        if (! $connection instanceof Connection) {
            return 'like';
        }

        return $connection->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
