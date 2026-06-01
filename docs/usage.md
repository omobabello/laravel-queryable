# laravel-queryable — Full Reference

This document covers every feature of `omoba/laravel-queryable` in depth. The [README](../README.md) is the quick-start; come here when you need the full picture.

---

## Table of contents

1. [Overview](#1-overview)
2. [Installation & setup](#2-installation--setup)
3. [Choosing your traits](#3-choosing-your-traits)
4. [Model declaration methods](#4-model-declaration-methods)
5. [Scopes reference](#5-scopes-reference)
6. [Filter operators — complete reference](#6-filter-operators--complete-reference)
7. [Special value behaviours](#7-special-value-behaviours)
8. [Relationship traversal](#8-relationship-traversal)
9. [Strict mode](#9-strict-mode)
10. [Chaining with Eloquent](#10-chaining-with-eloquent)
11. [REST API integration patterns](#11-rest-api-integration-patterns)
12. [Complete working example](#12-complete-working-example)
13. [Error handling](#13-error-handling)
14. [Testing queryable models](#14-testing-queryable-models)
15. [FAQ / troubleshooting](#15-faq--troubleshooting)
16. [Comparison with spatie/laravel-query-builder](#16-comparison-with-spatielaravel-query-builder)

---

## 1. Overview

### What problem it solves

Every Laravel API that exposes a list endpoint ends up re-implementing the same logic: search across a few columns, filter by status or date range, sort by a column the client picks. The implementation lives in the controller, or scattered across query scopes, and it diverges across models until it becomes unmaintainable.

`laravel-queryable` solves this by moving the declaration to the model ("these are the fields I allow you to search/filter/sort") and providing uniform scopes that honour it. Controllers become a one-liner. New models get full search/filter/sort in minutes.

### Core mental model

**Declare on the model, use in the scope.**

```
Model declares:
  searchable()   → which columns can be searched
  filterable()   → which columns can be filtered, and how
  sortable()     → which columns can be sorted

Controller uses:
  ->search($term)
  ->filter($filters)
  ->sort($spec)
```

The model is the single source of truth for what is permitted. The controller passes raw request values straight through — no per-field validation or field-guarding needed in the controller.

### What the package does NOT do

- It does not expose which fields to include in the response (no `fields` / `include` concept).
- It does not accept a `Request` object — scopes take plain PHP values, keeping them testable without an HTTP context.
- It does not support sorting by relationship columns (requires a join, which risks duplicates).
- It does not paginate — use Eloquent's own `->paginate()`.

---

## 2. Installation & setup

### Install

```bash
composer require omoba/laravel-queryable
```

Requires PHP 8.2+ and Laravel 10, 11, or 12.

### Service provider

The service provider is auto-discovered via Composer's `extra.laravel.providers` key. You do **not** need to add anything to `config/app.php`.

### Publish the config (optional)

```bash
php artisan vendor:publish --tag=queryable-config
```

This copies `config/queryable.php` into your application:

```php
// config/queryable.php
return [
    'strict' => env('QUERYABLE_STRICT', true),
];
```

The only option is `strict`. See [§9 Strict mode](#9-strict-mode) for what it does. If you don't publish the config, the default (`strict = true`) applies.

You can also set it per-environment without publishing:

```dotenv
# .env
QUERYABLE_STRICT=false
```

---

## 3. Choosing your traits

| Trait | Namespace | Scopes added | Declaration methods required |
|---|---|---|---|
| `Queryable` | `Omoba\LaravelQueryable\Concerns\Queryable` | `search`, `searchEncrypted`, `filter`, `filterHaving`, `sort` | `searchable()`, `filterable()`, `sortable()` |
| `Searchable` | `Omoba\LaravelQueryable\Concerns\Searchable` | `search`, `searchEncrypted` | `searchable()` |
| `Filterable` | `Omoba\LaravelQueryable\Concerns\Filterable` | `filter`, `filterHaving` | `filterable()` |
| `Sortable` | `Omoba\LaravelQueryable\Concerns\Sortable` | `sort` | `sortable()` |

**Use `Queryable`** when you want all three features. It is a composite trait that pulls in all three.

**Mix individual traits** when a model only needs a subset:

```php
use Omoba\LaravelQueryable\Concerns\Filterable;
use Omoba\LaravelQueryable\Concerns\Sortable;

class Product extends Model
{
    use Filterable, Sortable;

    public function filterable(): array
    {
        return [
            'category' => FilterOperator::Exact,
            'price'    => FilterOperator::Between,
        ];
    }

    public function sortable(): array
    {
        return ['name', 'price', 'created_at'];
    }
}
```

The PHP compiler will tell you which declaration methods are missing if you forget one — all declaration methods are abstract in the trait.

---

## 4. Model declaration methods

### `searchable(): array`

**Required by**: `Searchable` (and `Queryable`)

Returns an indexed array of column names and/or dot-notation relation paths. The `search()` scope performs a case-insensitive substring match across all of them.

```php
public function searchable(): array
{
    return [
        'name',                  // own column
        'email',                 // own column
        'company.name',          // relation: one level deep
        'team.company.name',     // relation: two levels deep
    ];
}
```

**Own columns** produce `LIKE '%term%'` (or `ILIKE` on PostgreSQL).

**Dot-notation paths** produce `orWhereHas('relation', fn($q) => $q->where('column', 'LIKE', '%term%'))`. Arbitrary depth is supported — `a.b.c` becomes `whereHas('a.b', ...)` matching on column `c`.

---

### `searchableEncrypted(): array`

**Required by**: `Searchable` (optional — defaults to `[]`)

Returns an indexed array of column names that store SHA-256 hashed values. Used exclusively by the `searchEncrypted()` scope, which hashes the input term before querying.

```php
public function searchableEncrypted(): array
{
    return ['phone_hash', 'national_id_hash'];
}
```

If you have no encrypted columns, you can omit this method entirely — the trait provides a default `[]`.

---

### `hashSearchTerm(string $term): string`

**Required by**: `Searchable` (optional override)

Defines how the search term is hashed for `searchEncrypted()`. The default is:

```php
public function hashSearchTerm(string $term): string
{
    return hash('sha256', $term);
}
```

Override when your hashing strategy differs — for example, if you normalise before hashing:

```php
public function hashSearchTerm(string $term): string
{
    return hash('sha256', strtolower(trim($term)));
}
```

The return value is matched exactly (`=`) against the declared encrypted columns.

---

### `filterable(): array`

**Required by**: `Filterable` (and `Queryable`)

Maps field names to filter operators. Three declaration styles are supported and can be mixed freely:

```php
use Omoba\LaravelQueryable\Operators\FilterOperator;

public function filterable(): array
{
    return [
        // Style 1: Explicit operator via enum constant
        'name'       => FilterOperator::Like,
        'email'      => FilterOperator::Exact,
        'status'     => FilterOperator::In,
        'created_at' => FilterOperator::DateRange,
        'archived_at'=> FilterOperator::Null,
        'amount'     => FilterOperator::Gte,

        // Style 2: Explicit operator via string (case-insensitive)
        'score'      => 'gte',
        'discount'   => 'lte',

        // Style 3: No operator — inferred from the incoming value at runtime
        'category',   // array with from/to → Between; else → Exact
        'updated_at', // array with from/to → Between; else → Exact
    ];
}
```

**Runtime inference** (style 3): when no operator is declared, the package inspects the incoming value:
- Value is an array with a `from` or `to` key → `Between` operator
- Anything else → `Exact` operator

Dot-notation relation paths work identically to `searchable()`:

```php
public function filterable(): array
{
    return [
        'company.industry'  => FilterOperator::Exact,
        'team.company.name' => FilterOperator::Like,
    ];
}
```

---

### `having(): array`

**Required by**: `Filterable` (optional — defaults to `[]`)

Same shape as `filterable()`, but for aggregate or computed columns used in a `HAVING` clause. Used by the `filterHaving()` scope.

```php
public function having(): array
{
    return [
        'posts_count'    => FilterOperator::Between,
        'total_revenue'  => FilterOperator::Gte,
    ];
}
```

Fields declared in `having()` are **not** available in `filter()`, and vice versa. They are intentionally separate because `WHERE` and `HAVING` target different stages of the query.

---

### `sortable(): array`

**Required by**: `Sortable` (and `Queryable`)

An indexed array of column names that the client is permitted to sort by. The field names here must be own columns on the model's table — relation sorting is not supported.

```php
public function sortable(): array
{
    return ['name', 'email', 'created_at', 'amount'];
}
```

If a field is not in this list, `sort()` either throws `InvalidSortField` (strict mode, default) or silently skips it (loose mode).

---

## 5. Scopes reference

### `search(?string $term)`

Performs a case-insensitive substring search across all columns declared in `searchable()`.

**Null/empty guard**: if `$term` is `null` or an empty string, the scope returns the query unmodified — no SQL is added.

**Logic**: all columns are OR'd together inside a single `WHERE (...)` group, so a match on any declared column satisfies the search. Multiple `filter()` calls AND'd with `search()` work correctly because the OR group is self-contained.

**Database driver**: uses `ILIKE` on PostgreSQL (natively case-insensitive), `LIKE` on MySQL, MariaDB, SQLite, and others (case-sensitivity depends on collation — see [troubleshooting](#15-faq--troubleshooting)).

```php
User::search('john')->get();
```

Equivalent SQL (pseudocode):
```sql
WHERE (
    name LIKE '%john%'
    OR email LIKE '%john%'
    OR EXISTS (
        SELECT 1 FROM companies
        WHERE companies.id = users.company_id
        AND companies.name LIKE '%john%'
    )
)
```

---

### `searchEncrypted(?string $term)`

Hashes `$term` using `hashSearchTerm()` and performs an exact match against columns declared in `searchableEncrypted()`.

**Null/empty guard**: same as `search()` — returns the query unmodified.

**Use case**: PII columns (phone numbers, national IDs, emails) that are stored as hashes for privacy. The plaintext is never stored; you search by hashing the input and matching the stored hash.

```php
// Model stores phone numbers as SHA-256 hashes
User::searchEncrypted('+15550100')->first();
```

Equivalent SQL:
```sql
WHERE phone_hash = 'a1b2c3d4...'  -- hash('+15550100')
```

Note: unlike `search()`, this is an **exact match**, not a substring match.

---

### `filter(?array $filters)`

Applies `WHERE` clauses for each key-value pair in `$filters`, using the operator declared (or inferred) in `filterable()`.

**Null/empty guard**: `null` and `[]` both return the query unmodified.

**Per-field empty guard**: a value of `null` or `''` for a specific field is silently skipped. This means you can pass `$request->array('filter')` directly without pre-filtering empty form fields.

**Logic**: all active fields are AND'd together.

**Strict/loose**: an unknown field key throws `InvalidFilterField` in strict mode, or is silently skipped in loose mode.

```php
User::filter([
    'status'           => 'active',
    'created_at'       => ['from' => '2025-01-01', 'to' => '2025-12-31'],
    'company.industry' => 'fintech',
    'archived_at'      => '',        // empty — silently skipped
])->get();
```

Equivalent SQL:
```sql
WHERE status = 'active'
AND created_at BETWEEN '2025-01-01 00:00:00' AND '2025-12-31 23:59:59'
AND EXISTS (
    SELECT 1 FROM companies
    WHERE companies.id = users.company_id
    AND companies.industry = 'fintech'
)
```

---

### `filterHaving(?array $filters)`

Same as `filter()` but emits `HAVING` clauses instead of `WHERE`. Use this after aggregation.

**When to use**: after `->withCount('relation')`, `->selectRaw('COUNT(*) as total')`, or any other expression that produces a computed column visible only at the HAVING stage.

**Strict/loose**: unknown fields are validated against `having()` (not `filterable()`). Throws `InvalidFilterField::notDeclaredForHaving()` in strict mode.

```php
User::query()
    ->withCount('posts')
    ->filterHaving(['posts_count' => ['from' => 5]])
    ->get();
```

Equivalent SQL (simplified):
```sql
SELECT users.*, COUNT(posts.id) AS posts_count
FROM users
LEFT JOIN posts ON posts.user_id = users.id
GROUP BY users.id
HAVING posts_count >= 5
```

---

### `sort(string|array|null $spec)`

Applies `ORDER BY` clauses for each field specified in `$spec`. Fields must be declared in `sortable()`.

**Null/empty guard**: `null`, `''`, and `[]` all return the query unmodified.

**Three input formats** — choose whichever fits your API convention:

**Format 1 — string** (recommended for query strings):
```php
// Prefix with '-' for descending order
User::sort('-created_at,name')->get();
// ORDER BY created_at DESC, name ASC

// Whitespace around commas is trimmed
User::sort(' -created_at , name ')->get();  // same result
```

**Format 2 — associative array**:
```php
User::sort(['created_at' => 'desc', 'name' => 'asc'])->get();
// ORDER BY created_at DESC, name ASC
```

**Format 3 — indexed array** (with `-` prefix or tuple pairs):
```php
User::sort(['-created_at', 'name'])->get();
// ORDER BY created_at DESC, name ASC

User::sort([['created_at', 'desc'], ['name', 'asc']])->get();
// ORDER BY created_at DESC, name ASC
```

**Direction normalisation**: an unrecognised direction value (anything other than `'desc'`) is silently treated as `'asc'`. This prevents exceptions from malformed client input while maintaining a predictable result.

**Strict/loose**: an unknown field throws `InvalidSortField` in strict mode, or is silently skipped in loose mode.

---

## 6. Filter operators — complete reference

### Overview table

| Enum constant | String alias | Input shape | SQL emitted |
|---|---|---|---|
| `FilterOperator::Exact` | `'exact'` | scalar · `'a,b'` · `['a','b']` | `= ?` or `IN (...)` |
| `FilterOperator::Like` | `'like'` | scalar | `LIKE '%value%'` |
| `FilterOperator::In` | `'in'` | `'a,b'` · `['a','b']` | `IN (...)` |
| `FilterOperator::Between` | `'between'` | `['from' => x, 'to' => y]` | `BETWEEN x AND y` · `>= x` · `<= y` |
| `FilterOperator::DateRange` | `'date_range'` | `['from' => date, 'to' => date]` | `BETWEEN` · `>=` · `<=` (Carbon-parsed) |
| `FilterOperator::Null` | `'null'` | (no value needed) | `IS NULL` |
| `FilterOperator::Gt` | `'gt'` | scalar | `> ?` |
| `FilterOperator::Gte` | `'gte'` | scalar | `>= ?` |
| `FilterOperator::Lt` | `'lt'` | scalar | `< ?` |
| `FilterOperator::Lte` | `'lte'` | scalar | `<= ?` |

---

### `Exact`

Matches a single value with `=`, or multiple values with `IN`. Multiple values can be passed as a PHP array or as a comma-separated string.

```php
// Declaration
'status' => FilterOperator::Exact,

// Single value → WHERE status = 'active'
->filter(['status' => 'active'])

// Array → WHERE status IN ('active', 'pending')
->filter(['status' => ['active', 'pending']])

// CSV string → WHERE status IN ('active', 'pending')
->filter(['status' => 'active,pending'])
```

---

### `Like`

Case-insensitive substring match. Wraps the value in `%...%`.

```php
// Declaration
'name' => FilterOperator::Like,

// WHERE name LIKE '%john%'
->filter(['name' => 'john'])
```

Uses `ILIKE` on PostgreSQL automatically. On other databases, case-sensitivity depends on column collation.

---

### `In`

Always produces `IN (...)`. Unlike `Exact`, a single value still emits `IN (val)` rather than `= val`. Use this when you always want array semantics.

```php
// Declaration
'role' => FilterOperator::In,

// WHERE role IN ('admin')
->filter(['role' => 'admin'])

// WHERE role IN ('admin', 'editor')
->filter(['role' => ['admin', 'editor']])

// WHERE role IN ('admin', 'editor')
->filter(['role' => 'admin,editor'])
```

---

### `Between`

Matches a numeric (or any comparable) range. Both `from` and `to` are optional — omitting one emits a one-sided comparison.

```php
// Declaration
'amount' => FilterOperator::Between,

// Both sides → WHERE amount BETWEEN 100 AND 500
->filter(['amount' => ['from' => 100, 'to' => 500]])

// Only from → WHERE amount >= 100
->filter(['amount' => ['from' => 100]])

// Only to → WHERE amount <= 500
->filter(['amount' => ['to' => 500]])
```

---

### `DateRange`

Identical to `Between` but treats values as dates. Parses `from` with `startOfDay()` (00:00:00) and `to` with `endOfDay()` (23:59:59), so a date-only string captures the full day.

```php
// Declaration
'created_at' => FilterOperator::DateRange,

// WHERE created_at BETWEEN '2025-01-01 00:00:00' AND '2025-12-31 23:59:59'
->filter(['created_at' => ['from' => '2025-01-01', 'to' => '2025-12-31']])

// WHERE created_at >= '2025-01-01 00:00:00'
->filter(['created_at' => ['from' => '2025-01-01']])
```

Values are parsed by Carbon, so any format Carbon accepts works (ISO 8601, `Y-m-d`, timestamps, etc.).

---

### `Null`

Declares that a column is always queried for `IS NULL`, regardless of the value passed by the client. The incoming value is ignored.

```php
// Declaration — this column will only ever emit IS NULL
'deactivated_at' => FilterOperator::Null,

// WHERE deactivated_at IS NULL (value '1' is ignored)
->filter(['deactivated_at' => '1'])
```

This is distinct from the `'null'` value sentinel (see [§7](#7-special-value-behaviours)), which works on any field regardless of declared operator.

---

### `Gt`, `Gte`, `Lt`, `Lte`

Scalar comparison operators. Pass a single value.

```php
// Declarations
'score'  => FilterOperator::Gt,
'price'  => FilterOperator::Lte,
'amount' => FilterOperator::Gte,
'rank'   => FilterOperator::Lt,

// WHERE score > 80
->filter(['score' => 80])

// WHERE price <= 99.99
->filter(['price' => 99.99])

// WHERE amount >= 5000
->filter(['amount' => 5000])

// WHERE rank < 10
->filter(['rank' => 10])
```

---

## 7. Special value behaviours

### Null sentinels

Two string values carry special meaning for **any** filterable field, regardless of its declared operator. They override the declared operator and emit a nullability check.

| Value passed | SQL emitted |
|---|---|
| `'null'` | `WHERE column IS NULL` |
| `'not_null'` | `WHERE column IS NOT NULL` |

```php
// In code
->filter(['archived_at' => 'null'])     // WHERE archived_at IS NULL
->filter(['archived_at' => 'not_null']) // WHERE archived_at IS NOT NULL
```

In a REST API, this means clients can test for nullability without a separate endpoint:

```
GET /users?filter[archived_at]=null      → WHERE archived_at IS NULL
GET /users?filter[archived_at]=not_null  → WHERE archived_at IS NOT NULL
```

This works even if `archived_at` is declared as `FilterOperator::DateRange`.

---

### CSV shorthand

For `Exact` and `In` operators, a comma-separated string is automatically expanded into an `IN (...)` clause:

```
GET /users?filter[status]=active,pending,suspended
→ WHERE status IN ('active', 'pending', 'suspended')
```

This is purely a convenience for query strings. In code, you can pass a PHP array directly:

```php
->filter(['status' => ['active', 'pending', 'suspended']])
```

Both forms produce the same SQL.

---

### Empty value skipping

A value of `null` or `''` (empty string) for a specific field is silently skipped — no SQL is emitted for that field. This is deliberate, and it means you can pass the raw filter array from a request directly without pre-cleaning:

```php
// Safe — empty fields from the form are automatically skipped
->filter($request->array('filter'))
```

If the entire `$filters` array is `null` or empty, the scope is a complete no-op.

---

## 8. Relationship traversal

Dot notation in `searchable()` and `filterable()` traverses Eloquent relationships of any depth. The package uses `whereHas` / `orWhereHas` internally — no joins are involved.

```
'company.name'        →  whereHas('company', fn($q) => $q->where('name', ...))
'team.company.name'   →  whereHas('team.company', fn($q) => $q->where('name', ...))
```

The last segment is always treated as the column; everything before it is the Eloquent relation path passed to `whereHas`. This means:
- The relation must be defined on the model (a standard Eloquent `BelongsTo`, `HasOne`, `HasMany`, etc.).
- Nesting depth is unlimited — Eloquent's `whereHas('a.b.c', ...)` handles the chain.
- Any relationship type that `whereHas` supports works.

**Sorting by relationship column is not supported.** Sorting via `whereHas` is not possible — it requires a `JOIN`, which can produce duplicate rows and column-name collisions. If you need to sort by a related column, use a raw `orderBy` with an explicit join in your query:

```php
User::query()
    ->join('companies', 'companies.id', '=', 'users.company_id')
    ->select('users.*')
    ->orderBy('companies.name')
    ->filter($request->array('filter'))
    ->get();
```

---

## 9. Strict mode

### What it does

By default, `strict` is `true`. When strict, passing a field name to `filter()`, `filterHaving()`, or `sort()` that is not declared in the corresponding model method throws an exception immediately.

This is intentional: it prevents a client from probing undeclared columns, and it surfaces misconfigured field names during development rather than silently returning wrong results.

### When to disable it

Turn off strict mode when you want to accept extra parameters gracefully — for example, a public-facing API where clients may send unused query params, or during a migration where old clients send fields that have been removed from the model.

```php
// config/queryable.php
return ['strict' => false];

// or in .env
QUERYABLE_STRICT=false
```

In loose mode, unknown fields are silently skipped and do not affect the query.

### Exceptions thrown in strict mode

| Exception | Thrown when |
|---|---|
| `InvalidFilterField` | A key in `filter()` is not declared in `filterable()` |
| `InvalidFilterField` | A key in `filterHaving()` is not declared in `having()` |
| `InvalidSortField` | A field in `sort()` is not declared in `sortable()` |

Exception messages include the field name and the model class name:

```
Filter field [bogus_field] is not declared in App\Models\User::filterable().
Sort field [secret_col] is not declared in App\Models\User::sortable().
```

### Catching exceptions

```php
use Omoba\LaravelQueryable\Exceptions\InvalidFilterField;
use Omoba\LaravelQueryable\Exceptions\InvalidSortField;
use Omoba\LaravelQueryable\Exceptions\QueryableException;

try {
    $users = User::filter($request->array('filter'))
                 ->sort($request->string('sort')->toString() ?: null)
                 ->paginate(25);
} catch (InvalidFilterField | InvalidSortField $e) {
    return response()->json(['error' => $e->getMessage()], 422);
}
```

Or catch the base class `QueryableException` to handle all package exceptions at once:

```php
} catch (QueryableException $e) {
    return response()->json(['error' => $e->getMessage()], 422);
}
```

The recommended approach for APIs is a global exception handler in `app/Exceptions/Handler.php`:

```php
use Omoba\LaravelQueryable\Exceptions\QueryableException;

public function register(): void
{
    $this->renderable(function (QueryableException $e) {
        return response()->json(['error' => $e->getMessage()], 422);
    });
}
```

---

## 10. Chaining with Eloquent

All five scopes return the Eloquent query builder, so they compose freely with any Eloquent method. Order does not matter — each scope independently adds its clause to the same builder.

### Basic chain

```php
User::query()
    ->whereNotNull('email_verified_at')
    ->with(['company', 'profile'])
    ->search($request->string('q')->toString() ?: null)
    ->filter($request->array('filter'))
    ->sort($request->string('sort')->toString() ?: null)
    ->paginate($request->integer('per_page', 25));
```

### Chain with HAVING

`filterHaving()` should come after the query methods that produce the aggregate column:

```php
User::query()
    ->withCount('posts')
    ->search($request->string('q')->toString() ?: null)
    ->filter($request->array('filter'))
    ->filterHaving($request->array('having'))
    ->sort($request->string('sort')->toString() ?: null)
    ->paginate(25);
```

### Model-level scopes and static calls

Because scopes are added to the Eloquent builder, you can call them as static entry points or chain them off existing scopes:

```php
// Static entry point
User::search('alice')->filter(['status' => 'active'])->get();

// Off an existing scope
User::active()->search('alice')->get();

// With eager loading
User::with('company')->search('alice')->paginate();
```

---

## 11. REST API integration patterns

### Recommended query string conventions

| Feature | Query param | Example |
|---|---|---|
| Search | `q` | `?q=john` |
| Filter | `filter[field]` | `?filter[status]=active` |
| Sort | `sort` | `?sort=-created_at,name` |
| Pagination | `per_page`, `page` | `?per_page=25&page=2` |

### Parsing request input

```php
// Search — coerce to string, map empty string to null (null is the no-op guard)
$term = $request->string('q')->toString() ?: null;

// Filters — array() returns [] if param missing, which is safe to pass directly
$filters = $request->array('filter');

// Sort — same as search: null is the no-op guard
$sort = $request->string('sort')->toString() ?: null;
```

### Complete controller method

```php
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;

public function index(Request $request): JsonResponse
{
    $users = User::query()
        ->search($request->string('q')->toString() ?: null)
        ->filter($request->array('filter'))
        ->sort($request->string('sort')->toString() ?: null)
        ->paginate($request->integer('per_page', 25));

    return response()->json($users);
}
```

### Example request patterns

```bash
# Full-text search
GET /users?q=alice

# Single exact filter
GET /users?filter[status]=active

# IN filter via CSV (no URL encoding needed for commas in most clients)
GET /users?filter[status]=active,pending

# Date range
GET /users?filter[created_at][from]=2025-01-01&filter[created_at][to]=2025-12-31

# Null check — archived users
GET /users?filter[archived_at]=not_null

# Sort descending by created_at
GET /users?sort=-created_at

# Multi-column sort
GET /users?sort=-created_at,name

# Combine everything
GET /users?q=alice&filter[status]=active&filter[created_at][from]=2025-01-01&sort=-created_at&per_page=10
```

---

## 12. Complete working example

A full, copy-paste-ready example using a `Transaction` model with all features exercised.

### Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Omoba\LaravelQueryable\Concerns\Queryable;
use Omoba\LaravelQueryable\Operators\FilterOperator;

class Transaction extends Model
{
    use Queryable;

    protected $casts = [
        'type'     => TransactionType::class,   // backed enum
        'status'   => TransactionStatus::class, // backed enum
        'category' => TransactionCategory::class,
    ];

    public function pocket(): BelongsTo
    {
        return $this->belongsTo(Pocket::class);
    }

    // --- Queryable declarations ---

    public function searchable(): array
    {
        return [
            'reference',
            'pocket.user.email',
            'pocket.user.first_name',
        ];
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

### Controller

```php
<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
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
}
```

### Route

```php
// routes/api.php
Route::get('/transactions', [TransactionController::class, 'index']);
```

### Example requests

```bash
# 1. Search across reference and user name/email
curl 'http://localhost:8000/api/transactions?q=john'

# 2. Exact filter on enum-cast column
curl 'http://localhost:8000/api/transactions?filter[status]=completed'

# 3. IN filter via CSV
curl 'http://localhost:8000/api/transactions?filter[status]=completed,pending'

# 4. Date range
curl 'http://localhost:8000/api/transactions?filter[created_at][from]=2026-01-01&filter[created_at][to]=2026-05-25'

# 5. Minimum amount (Gte)
curl 'http://localhost:8000/api/transactions?filter[amount]=10000'

# 6. Multi-column sort — newest first, then by amount ascending
curl 'http://localhost:8000/api/transactions?sort=-created_at,amount'

# 7. Null sentinel — transactions with no category
curl 'http://localhost:8000/api/transactions?filter[category]=null'

# 8. Strict-mode rejection — unknown field throws InvalidFilterField
curl 'http://localhost:8000/api/transactions?filter[bogus]=x'
# Set QUERYABLE_STRICT=false to make this skip silently instead

# 9. Combined real-world request
curl 'http://localhost:8000/api/transactions?q=jane&filter[status]=completed&filter[created_at][from]=2026-01-01&sort=-created_at'
```

---

## 13. Error handling

### Exception hierarchy

```
RuntimeException
└── QueryableException          (base for all package exceptions)
    ├── InvalidFilterField      (unknown field in filter / filterHaving)
    ├── InvalidSortField        (unknown field in sort)
    └── InvalidOperator         (unknown operator string in filterable declaration)
```

All are in the `Omoba\LaravelQueryable\Exceptions` namespace.

---

### `InvalidFilterField`

Thrown by `filter()` or `filterHaving()` when strict mode is on and a field key is not declared.

Two distinct messages:
- `Filter field [x] is not declared in Model::filterable().` — from `filter()`
- `Filter field [x] is not declared in Model::having().` — from `filterHaving()`

---

### `InvalidSortField`

Thrown by `sort()` when strict mode is on and a field is not declared in `sortable()`.

Message: `Sort field [x] is not declared in Model::sortable().`

---

### `InvalidOperator`

Thrown at model boot time (or at first query), not during a request, when `filterable()` returns a string operator that is not one of the ten known values.

Message: `Unknown filter operator [x].`

This is typically a typo in your model declaration — e.g., `'excat'` instead of `'exact'`. It is caught during development, not in production.

---

### Global handler pattern

The cleanest way to handle `InvalidFilterField` and `InvalidSortField` in an API is a single renderable in your exception handler:

```php
// app/Exceptions/Handler.php
use Omoba\LaravelQueryable\Exceptions\QueryableException;

public function register(): void
{
    $this->renderable(function (QueryableException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    });
}
```

---

## 14. Testing queryable models

### Setup

The package itself uses SQLite in-memory for feature tests. The same approach works in your application:

```php
// phpunit.xml or phpunit.xml.dist
<php>
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
</php>
```

### Testing search

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class UserSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_own_column(): void
    {
        User::factory()->create(['name' => 'Alice Smith']);
        User::factory()->create(['name' => 'Bob Jones']);

        $results = User::search('alice')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Alice Smith', $results->first()->name);
    }

    public function test_search_is_case_insensitive(): void
    {
        User::factory()->create(['name' => 'Alice Smith']);

        $this->assertCount(1, User::search('ALICE')->get());
        $this->assertCount(1, User::search('alice')->get());
    }

    public function test_search_null_returns_all(): void
    {
        User::factory()->count(3)->create();

        $this->assertCount(3, User::search(null)->get());
        $this->assertCount(3, User::search('')->get());
    }
}
```

### Testing filter

```php
public function test_filter_exact_operator(): void
{
    User::factory()->create(['status' => 'active']);
    User::factory()->create(['status' => 'pending']);

    $results = User::filter(['status' => 'active'])->get();

    $this->assertCount(1, $results);
}

public function test_filter_date_range(): void
{
    User::factory()->create(['created_at' => '2025-06-15']);
    User::factory()->create(['created_at' => '2025-01-01']);

    $results = User::filter([
        'created_at' => ['from' => '2025-06-01', 'to' => '2025-06-30'],
    ])->get();

    $this->assertCount(1, $results);
}

public function test_filter_null_sentinel(): void
{
    User::factory()->create(['archived_at' => null]);
    User::factory()->create(['archived_at' => now()]);

    $this->assertCount(1, User::filter(['archived_at' => 'null'])->get());
    $this->assertCount(1, User::filter(['archived_at' => 'not_null'])->get());
}
```

### Testing strict mode

```php
use Omoba\LaravelQueryable\Exceptions\InvalidFilterField;
use Omoba\LaravelQueryable\Exceptions\InvalidSortField;

public function test_strict_mode_throws_on_unknown_filter_field(): void
{
    $this->expectException(InvalidFilterField::class);

    User::filter(['undeclared_column' => 'value'])->get();
}

public function test_strict_mode_throws_on_unknown_sort_field(): void
{
    $this->expectException(InvalidSortField::class);

    User::sort('undeclared_column')->get();
}
```

### Testing with loose mode

```php
public function test_loose_mode_skips_unknown_fields(): void
{
    config(['queryable.strict' => false]);

    User::factory()->count(3)->create();

    // No exception — unknown field is silently skipped
    $this->assertCount(3, User::filter(['bogus' => 'value'])->get());
}
```

---

## 15. FAQ / troubleshooting

**`InvalidFilterField` is thrown even though I declared the field.**

Double-check the exact key you're passing to `filter()`. The key must match what you declared in `filterable()` exactly — including dot-notation relation paths. A typo in either place causes this. Also confirm the field is in `filterable()` and not only in `having()` (the two maps are separate).

---

**Search is case-sensitive on MySQL.**

The `LIKE` operator respects the column's collation. For case-insensitive behaviour on MySQL/MariaDB, the column (or the table default) must use a case-insensitive collation such as `utf8mb4_general_ci` or `utf8mb4_unicode_ci`. The `ci` suffix means "case-insensitive". On PostgreSQL, the package automatically uses `ILIKE`, which is always case-insensitive.

---

**`filterHaving` has no effect / returns all rows.**

`HAVING` filters are applied after grouping and aggregation. If your query has no `GROUP BY` and no aggregate in `SELECT`, the `HAVING` clause may not behave as expected. Ensure your query includes a `withCount()`, a `selectRaw('... AS alias')`, or an explicit `groupBy()` before calling `filterHaving()`.

---

**Search returns the correct rows but the count seems wrong.**

The package uses `EXISTS (SELECT 1 FROM ...)` for relation columns — not a `JOIN`. This means no row duplication: each parent row appears at most once in the result, regardless of how many related rows match. If the count still seems off, check whether your other query constraints (e.g., a `join()` you added manually) are causing duplication.

---

**Sort order is ignored.**

1. Confirm the field is in `sortable()`.
2. If strict mode is off and the field is not declared, it is silently skipped — no sort is applied. Enable strict mode temporarily to surface the issue.
3. Check that no other `orderBy()` call later in the chain is overriding the sort.

---

**`InvalidOperator` exception at model boot.**

You have a typo in a string operator in `filterable()`. Check every string value against the table in §6 — the valid values are: `exact`, `like`, `in`, `between`, `date_range`, `null`, `gt`, `gte`, `lt`, `lte`.

---

**`date_range` filter off by one day.**

`DateRange` applies `startOfDay()` to `from` and `endOfDay()` to `to`, so a `from=2025-01-01` becomes `>= 2025-01-01 00:00:00` and `to=2025-01-01` becomes `<= 2025-01-01 23:59:59`. If you're passing full timestamps (e.g. `2025-01-01T14:30:00`), use the `Between` operator instead — it does no date parsing.

---

## 16. Comparison with `spatie/laravel-query-builder`

Spatie's package and this one solve adjacent problems. Here is an honest comparison to help you pick:

| | `omoba/laravel-queryable` | `spatie/laravel-query-builder` |
|---|---|---|
| **Declaration location** | On the model (`filterable()`, `sortable()`, etc.) | At the call site (`AllowedFilter::exact(...)`) |
| **`search` as a first-class concept** | Yes — its own scope, distinct from filter | No — search is just another filter |
| **Request coupling** | None — scopes accept plain PHP values | Optional — has a `QueryBuilder::for($request)` path |
| **Custom filter classes** | No | Yes — `AllowedFilter::custom(...)` |
| **Includes / field selection** | No | Yes |
| **Relation sorting** | No | Yes (via join or subquery) |
| **Encrypted column search** | Yes | No |
| **`HAVING` clause support** | Yes | No |

**Pick `laravel-queryable` when**:
- You want declarations to live on the model, co-located with its other business logic.
- Search (substring match across multiple columns) is a core feature you reach for on most lists.
- You work with encrypted / hashed PII columns.
- You need `HAVING` filters for aggregated endpoints.

**Pick `spatie/laravel-query-builder` when**:
- You need custom filter classes or per-request filter logic.
- You need to expose which fields to include in the response.
- You need to sort by relationship columns.
- You prefer the fluent declaration style at the call site over the model method style.
