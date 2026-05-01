<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded;

use Illuminate\Support\ServiceProvider;
use Samaphp\LaravelBounded\Exceptions\InvalidConfigurationException;
use Samaphp\LaravelBounded\Transaction\Transaction;
use Samaphp\LaravelBounded\Validators\NoListenersValidator;
use Samaphp\LaravelBounded\Validators\NoModelHooksValidator;
use Samaphp\LaravelBounded\Validators\SingleActionControllerValidator;
use Samaphp\LaravelBounded\Validators\TestParityValidator;
use Samaphp\LaravelBounded\Validators\ZonePartitionValidator;

final class BoundedServiceProvider extends ServiceProvider
{
    public const VALIDATOR_TAG = 'bounded.validators';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bounded.php', 'bounded');

        $this->app->singleton(Transaction::class);

        $basePathValidators = [
            TestParityValidator::class,
            SingleActionControllerValidator::class,
            NoListenersValidator::class,
            NoModelHooksValidator::class,
        ];

        $this->app
            ->when($basePathValidators)
            ->needs('$basePath')
            ->give(fn ($app) => $app->basePath());

        $this->app
            ->when($basePathValidators)
            ->needs('$ignoredScanPaths')
            ->give(fn ($app) => $app['config']->get('bounded.ignore.paths', []));

        $this->app->tag([
            ZonePartitionValidator::class,
            ...$basePathValidators,
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
