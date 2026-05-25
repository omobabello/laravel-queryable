<?php

declare(strict_types=1);

namespace Omoba\LaravelQueryable;

use Illuminate\Support\ServiceProvider;

final class LaravelQueryableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/queryable.php', 'queryable');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/queryable.php' => $this->app->configPath('queryable.php'),
            ], 'queryable-config');
        }
    }
}
