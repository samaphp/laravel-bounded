<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded;

use Illuminate\Support\ServiceProvider;
use Samaphp\LaravelBounded\Transaction\Transaction;

final class BoundedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bounded.php', 'bounded');

        $this->app->singleton(Transaction::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/bounded.php' => $this->app->configPath('bounded.php'),
            ], 'bounded-config');
        }
    }
}
