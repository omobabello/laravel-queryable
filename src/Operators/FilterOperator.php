<?php

declare(strict_types=1);

namespace Omoba\LaravelQueryable\Operators;

use Omoba\LaravelQueryable\Exceptions\InvalidOperator;

enum FilterOperator: string
{
    case Exact = 'exact';
    case Like = 'like';
    case In = 'in';
    case Between = 'between';
    case DateRange = 'date_range';
    case Null = 'null';
    case Gt = 'gt';
    case Gte = 'gte';
    case Lt = 'lt';
    case Lte = 'lte';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new InvalidOperator("Unknown filter operator [{$value}].");
    }
}
