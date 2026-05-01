<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded;

use Illuminate\Support\ServiceProvider;
use Samaphp\LaravelBounded\Exceptions\InvalidConfigurationException;
use Samaphp\LaravelBounded\Transaction\Transaction;
use Samaphp\LaravelBounded\Validators\ZonePartitionValidator;

final class BoundedServiceProvider extends ServiceProvider
{
    public const VALIDATOR_TAG = 'bounded.validators';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bounded.php', 'bounded');

        $this->app->singleton(Transaction::class);

        $this->app->tag([
            ZonePartitionValidator::class,
        ], self::VALIDATOR_TAG);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/bounded.php' => $this->app->configPath('bounded.php'),
            ], 'bounded-config');
        }

        $this->ensureZonesPartition();
    }

    private function ensureZonesPartition(): void
    {
        $result = $this->app->make(ZonePartitionValidator::class)->validate();

        if ($result->passed()) {
            return;
        }

        throw InvalidConfigurationException::forZoneProblems($result->problems());
    }
}
