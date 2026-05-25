<?php

declare(strict_types=1);

namespace Omoba\LaravelSearchable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Omoba\LaravelSearchable\Concerns\Queryable;

class Company extends Model
{
    use Queryable;

    protected $guarded = [];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return array<int, string>
     */
    public function searchable(): array
    {
        return ['name', 'industry'];
    }

    /**
     * @return array<string, string>
     */
    public function filterable(): array
    {
        return [
            'name' => 'like',
            'industry' => 'exact',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function sortable(): array
    {
        return ['name', 'created_at'];
    }
}
