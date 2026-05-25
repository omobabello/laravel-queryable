<?php

declare(strict_types=1);

namespace Omoba\LaravelQueryable\Tests\Feature;

use Omoba\LaravelQueryable\Tests\Models\Company;
use Omoba\LaravelQueryable\Tests\Models\User;
use Omoba\LaravelQueryable\Tests\TestCase;

final class ChainingTest extends TestCase
{
    public function test_search_filter_sort_compose_cleanly(): void
    {
        $acme = Company::create(['name' => 'Acme', 'industry' => 'fintech']);
        $globex = Company::create(['name' => 'Globex', 'industry' => 'manufacturing']);

        User::create([
            'company_id' => $acme->id,
            'name' => 'Alice',
            'email' => 'alice@acme.com',
            'status' => 'active',
        ]);
        User::create([
            'company_id' => $acme->id,
            'name' => 'Alex',
            'email' => 'alex@acme.com',
            'status' => 'archived',
        ]);
        User::create([
            'company_id' => $globex->id,
            'name' => 'Alice',
            'email' => 'alice@globex.com',
            'status' => 'active',
        ]);

        $matches = User::search('Ali')
            ->filter([
                'status' => 'active',
                'company.industry' => 'fintech',
            ])
            ->sort('-email')
            ->pluck('email')
            ->all();

        $this->assertSame(['alice@acme.com'], $matches);
    }
}
