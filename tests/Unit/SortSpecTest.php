<?php

declare(strict_types=1);

namespace Omoba\LaravelSearchable\Tests\Unit;

use Omoba\LaravelSearchable\Support\SortSpec;
use PHPUnit\Framework\TestCase;

final class SortSpecTest extends TestCase
{
    public function test_empty_returns_empty(): void
    {
        $this->assertSame([], SortSpec::parse(null));
        $this->assertSame([], SortSpec::parse(''));
        $this->assertSame([], SortSpec::parse([]));
    }

    public function test_string_single_ascending(): void
    {
        $this->assertSame([['name', 'asc']], SortSpec::parse('name'));
    }

    public function test_string_single_descending(): void
    {
        $this->assertSame([['name', 'desc']], SortSpec::parse('-name'));
    }

    public function test_string_multi_column_preserves_order(): void
    {
        $this->assertSame(
            [['created_at', 'desc'], ['name', 'asc']],
            SortSpec::parse('-created_at,name'),
        );
    }

    public function test_string_trims_whitespace_and_skips_empty(): void
    {
        $this->assertSame(
            [['name', 'asc'], ['email', 'desc']],
            SortSpec::parse(' name , , -email '),
        );
    }

    public function test_associative_array(): void
    {
        $this->assertSame(
            [['name', 'desc'], ['email', 'asc']],
            SortSpec::parse(['name' => 'desc', 'email' => 'asc']),
        );
    }

    public function test_associative_array_normalizes_direction(): void
    {
        $this->assertSame(
            [['name', 'asc']],
            SortSpec::parse(['name' => 'INVALID']),
        );
    }

    public function test_indexed_array_with_minus_prefix(): void
    {
        $this->assertSame(
            [['created_at', 'desc'], ['id', 'asc']],
            SortSpec::parse(['-created_at', 'id']),
        );
    }

    public function test_indexed_array_with_tuples(): void
    {
        $this->assertSame(
            [['name', 'desc']],
            SortSpec::parse([['name', 'desc']]),
        );
    }
}
