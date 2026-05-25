<?php

declare(strict_types=1);

namespace Omoba\LaravelQueryable\Tests\Feature;

use Omoba\LaravelQueryable\Exceptions\InvalidSortField;
use Omoba\LaravelQueryable\Tests\Models\User;
use Omoba\LaravelQueryable\Tests\TestCase;

final class SortableTest extends TestCase
{
    public function test_null_spec_is_noop(): void
    {
        User::create(['name' => 'B', 'email' => 'b@example.com']);
        User::create(['name' => 'A', 'email' => 'a@example.com']);

        $this->assertSame(['B', 'A'], User::sort(null)->pluck('name')->all());
    }

    public function test_string_descending(): void
    {
        User::create(['name' => 'A', 'email' => 'a@example.com']);
        User::create(['name' => 'C', 'email' => 'c@example.com']);
        User::create(['name' => 'B', 'email' => 'b@example.com']);

        $this->assertSame(['C', 'B', 'A'], User::sort('-name')->pluck('name')->all());
    }

    public function test_multi_column(): void
    {
        User::create(['name' => 'Alice', 'email' => 'z@example.com']);
        User::create(['name' => 'Alice', 'email' => 'a@example.com']);
        User::create(['name' => 'Bob', 'email' => 'b@example.com']);

        $this->assertSame(
            ['z@example.com', 'a@example.com', 'b@example.com'],
            User::sort('name,-email')->pluck('email')->all(),
        );
    }

    public function test_unknown_field_throws_in_strict_mode(): void
    {
        $this->expectException(InvalidSortField::class);

        User::sort('totally_made_up_field')->get();
    }

    public function test_unknown_field_skipped_in_loose_mode(): void
    {
        config()->set('queryable.strict', false);
        User::create(['name' => 'A', 'email' => 'a@example.com']);

        $this->assertSame(1, User::sort('totally_made_up_field')->count());
    }
}
