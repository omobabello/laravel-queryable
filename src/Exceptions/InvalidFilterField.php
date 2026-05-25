<?php

declare(strict_types=1);

namespace Omoba\LaravelSearchable\Exceptions;

final class InvalidFilterField extends SearchableException
{
    public static function notDeclared(string $field, string $model): self
    {
        return new self("Filter field [{$field}] is not declared in {$model}::filterable().");
    }

    public static function notDeclaredForHaving(string $field, string $model): self
    {
        return new self("Filter field [{$field}] is not declared in {$model}::having().");
    }
}
