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

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12

## Installation

```bash
composer require omoba/laravel-queryable
```

The service provider is auto-discovered. Optionally publish the config:

```bash
php artisan vendor:publish --tag=queryable-config
```

---

## Quick start

Add the `Queryable` trait to your model and implement the three declaration methods:

```php
use Omoba\LaravelQueryable\Concerns\Queryable;
use Omoba\LaravelQueryable\Operators\FilterOperator;

class User extends Model
{
    use Queryable;

    public function searchable(): array
    {
        return ['name', 'email', 'company.name'];
    }

    public function filterable(): array
    {
        return [
            'status'     => FilterOperator::In,
            'created_at' => FilterOperator::DateRange,
        ];
    }

    public function sortable(): array
    {
        return ['name', 'created_at'];
    }
}
```

Then in your controller:

```php
public function index(Request $request): JsonResponse
{
    return response()->json(
        User::query()
            ->search($request->string('q')->toString() ?: null)
            ->filter($request->array('filter'))
            ->sort($request->string('sort')->toString() ?: null)
            ->paginate($request->integer('per_page', 25))
    );
}
```

---

## Traits

Use `Queryable` for all three features, or mix in individual traits:

| Trait        | Scopes added                        |
|--------------|-------------------------------------|
| `Queryable`  | `search`, `searchEncrypted`, `filter`, `filterHaving`, `sort` |
| `Searchable` | `search`, `searchEncrypted`         |
| `Filterable` | `filter`, `filterHaving`            |
| `Sortable`   | `sort`                              |

```php
use Omoba\LaravelQueryable\Concerns\Filterable;
use Omoba\LaravelQueryable\Concerns\Sortable;

class Product extends Model
{
    use Filterable, Sortable;

    // No searchable() needed — Searchable trait is not used
}
```

Each trait requires you to implement its declaration method(s). The compiler will tell you which ones are missing.

---

## Declaration methods

### `searchable(): array`

Required by `Searchable`. Return column names (or dot-notation relation paths) to include in substring search.

```php
public function searchable(): array
{
    return [
        'name',
        'email',
        'company.name',        // one level deep
        'team.company.name',   // two levels deep
    ];
}
```

### `searchableEncrypted(): array`

Optional — defaults to `[]`. Columns that store SHA-256 hashed values. Used by `searchEncrypted()`.

```php
public function searchableEncrypted(): array
{
    return ['phone_hash'];
}
```

### `hashSearchTerm(string $term): string`

Optional override. Defaults to `hash('sha256', $term)`. Override to change the hashing strategy.

```php
public function hashSearchTerm(string $term): string
{
    return hash('sha256', strtolower(trim($term)));
}
```

### `filterable(): array`

Required by `Filterable`. Maps field names to filter operators. Three declaration styles:

```php
public function filterable(): array
{
    return [
        // Explicit operator via enum
        'name'             => FilterOperator::Like,
        'email'            => FilterOperator::Exact,
        'status'           => FilterOperator::In,
        'created_at'       => FilterOperator::DateRange,
        'archived_at'      => FilterOperator::Null,
        'amount'           => FilterOperator::Gte,

        // Explicit operator via string (case-insensitive)
        'score'            => 'gte',

        // No operator — inferred from the incoming value shape at runtime
        'category',
        'updated_at',
    ];
}
```

When no operator is declared, the package infers one: an array with `from`/`to` keys becomes `between`; anything else becomes `exact`.

Dot notation works the same as in `searchable()`:

```php
public function filterable(): array
{
    return [
        'company.industry' => FilterOperator::Exact,
        'team.company.name' => FilterOperator::Like,
    ];
}
```

### `having(): array`

Optional — defaults to `[]`. Same shape as `filterable()`, but for columns in a `HAVING` clause (e.g. aggregates from `withCount` / `selectRaw`). Used by `filterHaving()`.

```php
public function having(): array
{
    return [
        'posts_count' => FilterOperator::Between,
    ];
}
```

### `sortable(): array`

Required by `Sortable`. An indexed list of column names permitted for sorting.

```php
public function sortable(): array
{
    return ['name', 'created_at', 'email'];
}
```

---

## Scopes

### `search(?string $term)`

Case-insensitive substring match across all columns declared in `searchable()`. Columns in related tables use `orWhereHas`. Returns the unmodified query if `$term` is `null` or empty.

Uses `ILIKE` on PostgreSQL and `LIKE` on all other drivers.

```php
User::search('john')->get();
// WHERE (name LIKE '%john%' OR email LIKE '%john%' OR EXISTS (SELECT ... company.name LIKE '%john%'))
```

### `searchEncrypted(?string $term)`

Hashes `$term` (default: SHA-256) and performs an exact match against columns in `searchableEncrypted()`. Returns the unmodified query if `$term` is null or empty.

```php
User::searchEncrypted('+15550100')->first();
// WHERE phone_hash = 'a1b2c3...'
```

### `filter(?array $filters)`

Applies `WHERE` clauses for each key in `$filters` against the `filterable()` map. Silently skips `null` and empty-string values.

```php
User::filter([
    'status'     => 'active',
    'created_at' => ['from' => '2025-01-01', 'to' => '2025-12-31'],
])->get();
```

### `filterHaving(?array $filters)`

Same as `filter()` but emits `HAVING` clauses. Use after aggregating with `withCount`, `selectRaw`, etc.

```php
User::withCount('posts')
    ->filterHaving(['posts_count' => ['from' => 5]])
    ->get();
// HAVING posts_count >= 5
```

### `sort(string|array|null $spec)`

Applies `ORDER BY` for each field in `$spec`. The field must be declared in `sortable()`. Returns the unmodified query on `null` or empty input.

```php
// String form — prefix with '-' for descending
User::sort('-created_at,name')->get();
// ORDER BY created_at DESC, name ASC

// Associative array
User::sort(['created_at' => 'desc', 'name' => 'asc'])->get();

// Indexed array
User::sort(['-created_at', 'name'])->get();
```

---

## Filter operators

| Operator     | Enum constant             | Value shape                              | SQL                        |
|--------------|---------------------------|------------------------------------------|----------------------------|
| `exact`      | `FilterOperator::Exact`   | `'foo'` · `'a,b,c'` · `['a','b','c']`  | `= ?` or `IN (...)`        |
| `like`       | `FilterOperator::Like`    | `'foo'`                                  | `LIKE '%foo%'`             |
| `in`         | `FilterOperator::In`      | `'a,b,c'` · `['a','b','c']`             | `IN (...)`                 |
| `between`    | `FilterOperator::Between` | `['from' => x, 'to' => y]` (each side optional) | `BETWEEN` · `>=` · `<=` |
| `date_range` | `FilterOperator::DateRange` | same as `between`, parsed as Carbon dates | `BETWEEN` · `>=` · `<=` |
| `null`       | `FilterOperator::Null`    | (no user value needed)                   | `IS NULL`                  |
| `gt`         | `FilterOperator::Gt`      | scalar                                   | `> ?`                      |
| `gte`        | `FilterOperator::Gte`     | scalar                                   | `>= ?`                     |
| `lt`         | `FilterOperator::Lt`      | scalar                                   | `< ?`                      |
| `lte`        | `FilterOperator::Lte`     | scalar                                   | `<= ?`                     |

### Value sentinels

Any field — regardless of its declared operator — recognises two special string values:

| Sent value   | SQL emitted    |
|--------------|----------------|
| `'null'`     | `IS NULL`      |
| `'not_null'` | `IS NOT NULL`  |

This lets API clients check for nullability without a separate endpoint:

```http
GET /users?filter[archived_at]=null      → WHERE archived_at IS NULL
GET /users?filter[archived_at]=not_null  → WHERE archived_at IS NOT NULL
```

### CSV shorthand

For `exact` and `in` operators, a comma-separated string expands to an `IN` clause:

```http
GET /users?filter[status]=active,pending
→ WHERE status IN ('active', 'pending')
```

---

## Relationship traversal

Dot notation in `searchable()` and `filterable()` traverses Eloquent relations of any depth. The package uses `whereHas` / `orWhereHas` internally:

```php
public function searchable(): array
{
    return [
        'name',
        'company.name',           // whereHas('company', fn($q) => $q->orWhere('name', ...))
        'team.company.name',      // whereHas('team.company', fn($q) => $q->orWhere('name', ...))
    ];
}

public function filterable(): array
{
    return [
        'company.industry'  => FilterOperator::Exact,
        'team.company.name' => FilterOperator::Like,
    ];
}
```

Relation **sorting** is not supported in this version — it requires joins that risk duplicate rows and column-name collisions. Use a raw `orderBy` with an explicit join if you need it.

---

## Strict mode

By default, an unknown key in `filter()` / `filterHaving()` / `sort()` throws an exception.

| Exception           | Thrown when                                  |
|---------------------|----------------------------------------------|
| `InvalidFilterField` | Key not declared in `filterable()` / `having()` |
| `InvalidSortField`   | Field not declared in `sortable()`           |

Disable strict mode to silently skip unknown keys — useful for public APIs where clients may send extra parameters:

```php
// config/queryable.php
return ['strict' => false];

// or in .env
QUERYABLE_STRICT=false
```

---

## Chaining with native Eloquent

All scopes return the builder, so they compose freely with any Eloquent method:

```php
User::query()
    ->whereNotNull('email_verified_at')
    ->with(['company', 'profile'])
    ->search($request->string('q')->toString() ?: null)
    ->filter($request->array('filter'))
    ->sort($request->string('sort')->toString() ?: null)
    ->paginate(25);
```

### HAVING example

```php
User::query()
    ->withCount('posts')
    ->search($request->string('q')->toString() ?: null)
    ->filter($request->array('filter'))
    ->filterHaving($request->array('having'))
    ->sort($request->string('sort')->toString() ?: null)
    ->paginate(25);
```

---

## Full model example

```php
use Omoba\LaravelQueryable\Concerns\Queryable;
use Omoba\LaravelQueryable\Operators\FilterOperator;

class Transaction extends Model
{
    use Queryable;

    public function searchable(): array
    {
        return ['reference', 'pocket.user.email', 'pocket.user.first_name'];
    }

    public function searchableEncrypted(): array
    {
        return [];
    }

    public function filterable(): array
    {
        return [
            'type'       => FilterOperator::Exact,
            'category'   => FilterOperator::Exact,
            'status'     => FilterOperator::In,
            'created_at' => FilterOperator::DateRange,
            'amount'     => FilterOperator::Gte,
        ];
    }

    public function sortable(): array
    {
        return ['created_at', 'amount'];
    }
}
```

Controller:

```php
public function index(Request $request): JsonResponse
{
    $transactions = Transaction::query()
        ->search($request->string('q')->toString() ?: null)
        ->filter($request->array('filter'))
        ->sort($request->string('sort')->toString() ?: null)
        ->with('pocket.user')
        ->paginate($request->integer('per_page', 25));

    return response()->json($transactions);
}
```

Example requests:

```bash
# Search across reference and user name/email
GET /transactions?q=john

# Exact filter
GET /transactions?filter[status]=completed

# IN filter (CSV)
GET /transactions?filter[status]=completed,pending

# Date range
GET /transactions?filter[created_at][from]=2026-01-01&filter[created_at][to]=2026-05-25

# Minimum amount
GET /transactions?filter[amount]=10000

# Sort — newest first, then by amount ascending
GET /transactions?sort=-created_at,amount

# Null sentinel
GET /transactions?filter[category]=null

# Combined
GET /transactions?q=jane&filter[status]=completed&filter[created_at][from]=2026-01-01&sort=-created_at
```

---

## Comparison with `spatie/laravel-query-builder`

Spatie's package is broader in scope (filters, fields, includes, sorts, custom filters). This package takes a different approach:

- **Model-side declaration** — `filterable()`, `sortable()`, `searchable()` live on the model, not in a fluent chain at the call site.
- **First-class `search`** as its own concept, distinct from a filter.
- **No request coupling** — scopes accept plain PHP values, not a `Request` object.

Pick whichever fits your team's mental model.

---

## Testing

```bash
composer install
composer test      # PHPUnit
composer stan      # PHPStan level 8 + Larastan
vendor/bin/pint --test  # code style check
```

## License

MIT
