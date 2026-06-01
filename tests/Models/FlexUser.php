<?php

declare(strict_types=1);

namespace Omoba\LaravelQueryable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Omoba\LaravelQueryable\Concerns\Queryable;

class FlexUser extends Model
{
    use Queryable;

    protected $table = 'users';

    protected $guarded = [];

    public function searchable(): array
    {
        return [];
    }

    public function filterable(): array
    {
        return ['name', 'status', 'created_at'];
    }

    public function sortable(): array
    {
        return [];
    }
}
