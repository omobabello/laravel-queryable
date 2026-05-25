<?php

declare(strict_types=1);

namespace Omoba\LaravelQueryable\Tests\Feature;

use Omoba\LaravelQueryable\Exceptions\InvalidFilterField;
use Omoba\LaravelQueryable\Tests\Models\Company;
use Omoba\LaravelQueryable\Tests\Models\User;
use Omoba\LaravelQueryable\Tests\TestCase;

final class FilterableTest extends TestCase
{
    public function test_empty_filters_is_noop(): void
    {
        User::create(['name' => 'Alice', 'email' => 'a@example.com']);

        $this->assertSame(1, User::filter(null)->count());
        $this->assertSame(1, User::filter([])->count());
    }

    public function test_like_filter(): void
    {
        User::create(['name' => 'Alice', 'email' => 'a@example.com']);
        User::create(['name' => 'Bob', 'email' => 'b@example.com']);

        $matches = User::filter(['name' => 'lice'])->pluck('name')->all();
        $this->assertSame(['Alice'], $matches);
    }

    public function test_exact_filter_single_value(): void
    {
        User::create(['name' => 'Alice', 'email' => 'a@example.com']);
        User::create(['name' => 'Bob', 'email' => 'b@example.com']);

        $matches = User::filter(['email' => 'a@example.com'])->pluck('name')->all();
        $this->assertSame(['Alice'], $matches);
    }

    public function test_in_filter_via_comma_separated_string(): void
    {
        User::create(['name' => 'A', 'email' => 'a@example.com', 'status' => 'active']);
        User::create(['name' => 'B', 'email' => 'b@example.com', 'status' => 'pending']);
        User::create(['name' => 'C', 'email' => 'c@example.com', 'status' => 'archived']);

        $matches = User::filter(['status' => 'active,pending'])->pluck('name')->all();

        sort($matches);
        $this->assertSame(['A', 'B'], $matches);
    }

    public function test_in_filter_via_array(): void
    {
        User::create(['name' => 'A', 'email' => 'a@example.com', 'status' => 'active']);
        User::create(['name' => 'B', 'email' => 'b@example.com', 'status' => 'pending']);
        User::create(['name' => 'C', 'email' => 'c@example.com', 'status' => 'archived']);

        $matches = User::filter(['status' => ['active', 'archived']])->pluck('name')->all();

        sort($matches);
        $this->assertSame(['A', 'C'], $matches);
    }

    public function test_date_range_both_bounds(): void
    {
        $a = User::create(['name' => 'A', 'email' => 'a@example.com']);
        $a->forceFill(['created_at' => '2025-06-15 10:00:00'])->save();
        $b = User::create(['name' => 'B', 'email' => 'b@example.com']);
        $b->forceFill(['created_at' => '2026-01-01 10:00:00'])->save();

        $matches = User::filter([
            'created_at' => ['from' => '2025-01-01', 'to' => '2025-12-31'],
        ])->pluck('name')->all();

        $this->assertSame(['A'], $matches);
    }

    public function test_date_range_from_only(): void
    {
        $a = User::create(['name' => 'A', 'email' => 'a@example.com']);
        $a->forceFill(['created_at' => '2025-06-15'])->save();
        $b = User::create(['name' => 'B', 'email' => 'b@example.com']);
        $b->forceFill(['created_at' => '2026-01-01'])->save();

        $matches = User::filter([
            'created_at' => ['from' => '2025-12-01'],
        ])->pluck('name')->all();

        $this->assertSame(['B'], $matches);
    }

    public function test_null_sentinel_triggers_where_null_regardless_of_operator(): void
    {
        User::create(['name' => 'A', 'email' => 'a@example.com', 'archived_at' => null]);
        $b = User::create(['name' => 'B', 'email' => 'b@example.com']);
        $b->forceFill(['archived_at' => now()])->save();

        $matches = User::filter(['archived_at' => 'null'])->pluck('name')->all();
        $this->assertSame(['A'], $matches);

        $matches = User::filter(['archived_at' => 'not_null'])->pluck('name')->all();
        $this->assertSame(['B'], $matches);
    }

    public function test_relation_filter_via_dot_notation(): void
    {
        $acme = Company::create(['name' => 'Acme', 'industry' => 'fintech']);
        $globex = Company::create(['name' => 'Globex', 'industry' => 'manufacturing']);

        User::create(['company_id' => $acme->id, 'name' => 'Alice', 'email' => 'a@example.com']);
        User::create(['company_id' => $globex->id, 'name' => 'Bob', 'email' => 'b@example.com']);

        $matches = User::filter(['company.industry' => 'fintech'])->pluck('name')->all();
        $this->assertSame(['Alice'], $matches);
    }

    public function test_unknown_field_throws_in_strict_mode(): void
    {
        $this->expectException(InvalidFilterField::class);

        User::filter(['unknown_field' => 'value'])->get();
    }

    public function test_unknown_field_is_skipped_in_loose_mode(): void
    {
        config()->set('queryable.strict', false);
        User::create(['name' => 'Alice', 'email' => 'a@example.com']);

        $this->assertSame(1, User::filter(['unknown_field' => 'value'])->count());
    }

    public function test_empty_filter_values_are_skipped(): void
    {
        User::create(['name' => 'Alice', 'email' => 'a@example.com']);

        $matches = User::filter(['name' => '', 'email' => null])->pluck('name')->all();
        $this->assertSame(['Alice'], $matches);
    }
}
