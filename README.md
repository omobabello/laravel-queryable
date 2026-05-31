# laravel-queryable

Declarative `search`, `filter`, and `sort` scopes for Eloquent models — with first-class support for relationship traversal via dot notation.

```php
User::search('john')
    ->filter([
        'status'           => 'active,pending',
        'created_at'       => ['from' => '2025-01-01', 'to' => '2025-12-31'],
        'company.industry' => 'fintech',
    ])
    ->sort('-created_at,name')
    ->paginate();
```

## Installation

```bash
composer require omoba/laravel-queryable
```

The service provider is auto-discovered. Publish the (optional) config:

```bash
php artisan vendor:publish --tag=queryable-config
```

## Declaring search/filter/sort on a model

Add the `Queryable` trait (or pick `Searchable`, `Filterable`, `Sortable` individually) and override the relevant declaration methods.

```php
use Omoba\LaravelQueryable\Concerns\Queryable;
use Omoba\LaravelQueryable\Operators\FilterOperator;

class User extends Model
{
    use Queryable;

    public function searchable(): array
    {
        return ['name', 'email', 'company.name', 'profile.bio'];
    }

    public function searchableEncrypted(): array
    {
        return ['phone_hash'];
    }

    public function filterable(): array
    {
        return [
            'name'             => FilterOperator::Like,
            'email'            => FilterOperator:Exact,
            'status'           => FilterOperator:In,
            'created_at'       => FilterOperator:DateRange,
            'company.industry' => FilterOperator:Exact,
            'archived_at'      => FilterOperator:Null,
            'orders_count'     => FilterOperator::Gte,
        ];
    }

    public function having(): array
    {
        return ['orders_count' => 'gte'];
    }

    public function sortable(): array
    {
        return ['name', 'created_at', 'email'];
    }
}
```

All four declaration methods are optional. Returning `[]` (or not defining the method at all) makes the corresponding scope a no-op for that model.

## Scopes

| Scope                      | Input                                          | Notes                                                            |
| -------------------------- | ---------------------------------------------- | ---------------------------------------------------------------- |
| `search(?string $term)`    | string or null                                 | Case-insensitive substring match across `searchable()` columns / relations (uses `ILIKE` on Postgres, `LIKE` elsewhere) |
| `searchEncrypted($term)`   | string or null                                 | Exact match against `sha256($term)` over `searchableEncrypted()` |
| `filter(?array $filters)`  | `['field' => value, …]`                        | Operators driven by `filterable()` map                            |
| `filterHaving($filters)`   | same shape as `filter`                         | Emits `HAVING` clauses; use after `withCount` / `selectRaw`       |
| `sort(string\|array\|null)` | `'-created_at,name'` or `['name' => 'desc']` | Multi-column; field must be in `sortable()`                       |

All scopes safely no-op on empty/null input.

## Filter operators

| Operator     | Value shape                                | SQL                  |
| ------------ | ------------------------------------------ | -------------------- |
| `exact`      | `'foo'` or `'a,b,c'` or `['a','b','c']`    | `=` or `IN (...)`    |
| `like`       | `'foo'`                                    | `LIKE %foo%` (`ILIKE` on Postgres) |
| `in`         | `'a,b,c'` or `['a','b','c']`               | `IN (...)`           |
| `between`    | `['from' => x, 'to' => y]` (any side optional) | `BETWEEN`, `>=`, or `<=` |
| `date_range` | same as `between`, parsed as Carbon dates  | `BETWEEN`, `>=`, or `<=` |
| `null`       | (the operator-side default; see below)     | `IS NULL`            |
| `gt` / `gte` | scalar                                     | `>` / `>=`           |
| `lt` / `lte` | scalar                                     | `<` / `<=`           |

Any field — regardless of declared operator — also honors two value sentinels:

- value `'null'`     → `IS NULL`
- value `'not_null'` → `IS NOT NULL`

This makes API clients trivial: send `?filter[archived_at]=null` to grab non-archived rows without a separate endpoint.

## Relationship traversal

Use dot notation in `searchable()` or `filterable()` to traverse relations. The package uses `whereHas` (filter) and `orWhereHas` (search), so nesting works:

```php
public function searchable(): array
{
    return ['name', 'company.name', 'company.industry', 'team.company.name'];
}

public function filterable(): array
{
    return [
        'company.industry'  => 'exact',
        'team.company.name' => 'like',
    ];
}
```

Relationship **sorting** is intentionally not supported in v1 — it requires joins, which risk duplicate rows and column collisions. Use a separate `orderBy` with an explicit join if you need it.

## Strict vs loose mode

By default, an unknown filter or sort field throws an exception. Set `queryable.strict` to `false` (or env `QUERYABLE_STRICT=false`) to silently skip unknown keys — useful for lenient public APIs.

## Chaining with native query methods

The scopes return the builder, so you can interleave with anything from Eloquent:

```php
User::query()
    ->whereNotNull('email_verified_at')
    ->search($request->q)
    ->filter($request->filter ?? [])
    ->sort($request->sort)
    ->with(['company', 'profile'])
    ->paginate(25);
```

## Comparison with `spatie/laravel-query-builder`

Spatie's package is excellent and broader in scope (filters, fields, includes, sorts, custom filters). This package is narrower and deliberately different in shape:

- **Model-side declaration** via simple methods returning arrays — no fluent `allowedFilters([...])` chain at the call site.
- **First-class `search`** as its own concept, not a "partial filter".
- **No request coupling** — scopes take explicit arguments.

Pick whichever fits your team's mental model.

## Testing

```bash
composer install
composer test
composer stan
```

## License

MIT
