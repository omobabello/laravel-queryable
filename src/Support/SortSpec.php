<?php

declare(strict_types=1);

namespace Omoba\LaravelQueryable\Support;

/**
 * Parses sort specifications into an ordered list of (field, direction) pairs.
 *
 * Accepts:
 *  - "name"                       → [['name', 'asc']]
 *  - "-created_at"                → [['created_at', 'desc']]
 *  - "-created_at,name"           → [['created_at', 'desc'], ['name', 'asc']]
 *  - ['name' => 'desc']           → [['name', 'desc']]
 *  - [['name', 'desc'], 'email']  → [['name', 'desc'], ['email', 'asc']]
 */
final class SortSpec
{
    /**
     * @param  string|array<int|string, mixed>|null  $spec
     * @return array<int, array{0: string, 1: 'asc'|'desc'}>
     */
    public static function parse(string|array|null $spec): array
    {
        if ($spec === null || $spec === '' || $spec === []) {
            return [];
        }

        if (is_string($spec)) {
            return self::parseString($spec);
        }

        return self::parseArray($spec);
    }

    /**
     * @return array<int, array{0: string, 1: 'asc'|'desc'}>
     */
    private static function parseString(string $spec): array
    {
        $pairs = [];
        foreach (explode(',', $spec) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            $direction = 'asc';
            if (str_starts_with($segment, '-')) {
                $direction = 'desc';
                $segment = substr($segment, 1);
            }
            if ($segment === '') {
                continue;
            }
            $pairs[] = [$segment, $direction];
        }

        return $pairs;
    }

    /**
     * @param  array<int|string, mixed>  $spec
     * @return array<int, array{0: string, 1: 'asc'|'desc'}>
     */
    private static function parseArray(array $spec): array
    {
        $pairs = [];
        foreach ($spec as $key => $value) {
            if (is_int($key)) {
                if (is_array($value) && count($value) >= 1) {
                    $field = (string) ($value[0] ?? '');
                    $direction = self::normalizeDirection($value[1] ?? 'asc');
                } else {
                    $field = (string) $value;
                    $direction = 'asc';
                    if (str_starts_with($field, '-')) {
                        $direction = 'desc';
                        $field = substr($field, 1);
                    }
                }
            } else {
                $field = (string) $key;
                $direction = self::normalizeDirection((string) $value);
            }
            if ($field === '') {
                continue;
            }
            $pairs[] = [$field, $direction];
        }

        return $pairs;
    }

    /**
     * @return 'asc'|'desc'
     */
    private static function normalizeDirection(mixed $direction): string
    {
        $normalized = strtolower((string) $direction);

        return $normalized === 'desc' ? 'desc' : 'asc';
    }
}
