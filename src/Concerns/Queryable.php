<?php

declare(strict_types=1);

namespace Omoba\LaravelQueryable\Concerns;

/**
 * Convenience trait composing {@see Searchable}, {@see Filterable}, and {@see Sortable}.
 *
 * Use this when a model wants the full search/filter/sort surface. Pick the
 * individual traits when you only need a subset.
 */
trait Queryable
{
    use Filterable;
    use Searchable;
    use Sortable;
}
