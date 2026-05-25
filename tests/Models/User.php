<?php

declare(strict_types=1);

namespace Omoba\LaravelQueryable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Omoba\LaravelQueryable\Concerns\Queryable;
use Omoba\LaravelQueryable\Operators\FilterOperator;

class User extends Model
{
    use Queryable;

    protected $guarded = [];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasOne<Profile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @return array<int, string>
     */
    public function searchable(): array
    {
        return ['name', 'email', 'company.name', 'profile.bio'];
    }

    /**
     * @return array<int, string>
     */
    public function searchableEncrypted(): array
    {
        return ['phone_hash'];
    }

    /**
     * @return array<string, FilterOperator|string>
     */
    public function filterable(): array
    {
        return [
            'name' => FilterOperator::Like,
            'email' => 'exact',
            'status' => 'in',
            'created_at' => 'date_range',
            'company.industry' => 'exact',
            'archived_at' => 'null',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function having(): array
    {
        return [
            'posts_count' => 'between',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function sortable(): array
    {
        return ['name', 'email', 'created_at'];
    }
}
