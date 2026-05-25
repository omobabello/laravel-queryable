<?php

declare(strict_types=1);

namespace Omoba\LaravelQueryable\Exceptions;

final class InvalidSortField extends QueryableException
{
    public static function notDeclared(string $field, string $model): self
    {
        return new self("Sort field [{$field}] is not declared in {$model}::sortable().");
    }
}
