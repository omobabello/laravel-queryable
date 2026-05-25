<?php

declare(strict_types=1);

namespace Omoba\LaravelSearchable\Tests\Feature;

use Omoba\LaravelSearchable\Tests\Models\Company;
use Omoba\LaravelSearchable\Tests\Models\Profile;
use Omoba\LaravelSearchable\Tests\Models\User;
use Omoba\LaravelSearchable\Tests\TestCase;

final class SearchableTest extends TestCase
{
    public function test_null_or_empty_term_is_noop(): void
    {
        User::create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $this->assertSame(1, User::search(null)->count());
        $this->assertSame(1, User::search('')->count());
    }

    public function test_substring_match_across_local_columns(): void
    {
        User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
        User::create(['name' => 'Bob', 'email' => 'b.smith@example.com']);

        $names = User::search('lice')->pluck('name')->all();

        $this->assertSame(['Alice'], $names);
    }

    public function test_search_matches_via_belongs_to_relation(): void
    {
        $acme = Company::create(['name' => 'Acme', 'industry' => 'fintech']);
        $globex = Company::create(['name' => 'Globex', 'industry' => 'manufacturing']);

        $alice = User::create([
            'company_id' => $acme->id,
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);
        User::create([
            'company_id' => $globex->id,
            'name' => 'Bob',
            'email' => 'bob@example.com',
        ]);

        $matches = User::search('Acme')->pluck('id')->all();

        $this->assertSame([$alice->id], $matches);
    }

    public function test_search_matches_via_has_one_relation(): void
    {
        $alice = User::create(['name' => 'Alice', 'email' => 'a@example.com']);
        Profile::create(['user_id' => $alice->id, 'bio' => 'loves rock climbing']);

        $bob = User::create(['name' => 'Bob', 'email' => 'b@example.com']);
        Profile::create(['user_id' => $bob->id, 'bio' => 'enjoys baking']);

        $matches = User::search('climbing')->pluck('id')->all();

        $this->assertSame([$alice->id], $matches);
    }

    public function test_search_or_groups_across_columns_and_relations(): void
    {
        $acme = Company::create(['name' => 'Acme', 'industry' => 'fintech']);
        $alice = User::create([
            'company_id' => $acme->id,
            'name' => 'Alice',
            'email' => 'a@example.com',
        ]);
        $bob = User::create(['name' => 'Bob Acme-Lover', 'email' => 'b@example.com']);

        $matches = User::search('Acme')->pluck('id')->all();

        sort($matches);
        $expected = [$alice->id, $bob->id];
        sort($expected);
        $this->assertSame($expected, $matches);
    }

    public function test_search_group_ands_with_additional_where(): void
    {
        User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active']);
        User::create(['name' => 'Alice', 'email' => 'alice2@example.com', 'status' => 'archived']);

        $matches = User::search('Alice')->where('status', 'active')->pluck('email')->all();

        $this->assertSame(['alice@example.com'], $matches);
    }

    public function test_search_encrypted_hashes_term_before_comparing(): void
    {
        $phone = '+15550100';
        $hashed = hash('sha256', $phone);

        $alice = User::create([
            'name' => 'Alice',
            'email' => 'a@example.com',
            'phone_hash' => $hashed,
        ]);
        User::create([
            'name' => 'Bob',
            'email' => 'b@example.com',
            'phone_hash' => hash('sha256', '+15550999'),
        ]);

        $matches = User::searchEncrypted($phone)->pluck('id')->all();

        $this->assertSame([$alice->id], $matches);
    }
}
