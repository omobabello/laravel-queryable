<?php

declare(strict_types=1);

namespace Omoba\LaravelSearchable\Tests\Feature;

use Omoba\LaravelSearchable\Tests\Models\Post;
use Omoba\LaravelSearchable\Tests\Models\User;
use Omoba\LaravelSearchable\Tests\TestCase;

final class HavingTest extends TestCase
{
    public function test_having_filter_on_aggregated_column(): void
    {
        $alice = User::create(['name' => 'Alice', 'email' => 'a@example.com']);
        $bob = User::create(['name' => 'Bob', 'email' => 'b@example.com']);
        $charlie = User::create(['name' => 'Charlie', 'email' => 'c@example.com']);

        Post::create(['user_id' => $alice->id, 'title' => 'p1']);
        Post::create(['user_id' => $alice->id, 'title' => 'p2']);
        Post::create(['user_id' => $alice->id, 'title' => 'p3']);
        Post::create(['user_id' => $bob->id, 'title' => 'p1']);

        $matches = User::query()
            ->leftJoin('posts', 'posts.user_id', '=', 'users.id')
            ->groupBy('users.id')
            ->selectRaw('users.*, COUNT(posts.id) as posts_count')
            ->filterHaving(['posts_count' => ['from' => 3]])
            ->pluck('name')
            ->all();

        $this->assertSame(['Alice'], $matches);
        $this->assertNotContains('Charlie', $matches);
    }

    public function test_having_skips_unknown_when_loose(): void
    {
        config()->set('searchable.strict', false);
        User::create(['name' => 'Alice', 'email' => 'a@example.com']);

        $this->assertSame(1, User::query()->filterHaving(['nope' => 1])->count());
    }
}
