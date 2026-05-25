<?php

declare(strict_types=1);

namespace Omoba\LaravelSearchable\Support;

final class RelationPath
{
    public function __construct(
        public readonly string $relation,
        public readonly string $column,
    ) {}

    public static function parse(string $path): self
    {
        $segments = explode('.', $path);
        $column = array_pop($segments);

        return new self(implode('.', $segments), $column);
    }

    public function hasRelation(): bool
    {
        return $this->relation !== '';
    }
}
