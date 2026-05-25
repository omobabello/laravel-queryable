<?php

declare(strict_types=1);

namespace Omoba\LaravelSearchable;

use Illuminate\Support\ServiceProvider;

final class LaravelSearchableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/searchable.php', 'searchable');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/searchable.php' => $this->app->configPath('searchable.php'),
            ], 'searchable-config');
        }
    }
}
