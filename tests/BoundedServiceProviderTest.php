<?php

declare(strict_types=1);

use Samaphp\LaravelBounded\BoundedServiceProvider;
use Samaphp\LaravelBounded\Exceptions\InvalidConfigurationException;

it('registers the service provider', function () {
    expect($this->app->getLoadedProviders())
        ->toHaveKey(BoundedServiceProvider::class);
});

it('merges the bounded config under the bounded key', function () {
    expect(config('bounded'))
        ->toBeArray()
        ->toHaveKey('zones')
        ->toHaveKey('ignore');
});

it('exposes the bounded-config publish tag', function () {
    $publishables = BoundedServiceProvider::pathsToPublish(
        BoundedServiceProvider::class,
        'bounded-config',
    );

    expect($publishables)->not->toBeEmpty();
});

it('throws InvalidConfigurationException on boot when zones overlap', function () {
    config()->set('bounded.zones', [
        'logic' => ['app/Foo'],
        'repository' => ['app/Foo'],
    ]);

    $provider = new BoundedServiceProvider($this->app);

    expect(fn () => $provider->boot())
        ->toThrow(InvalidConfigurationException::class);
});

it('does not throw on boot when zones partition cleanly', function () {
    $provider = new BoundedServiceProvider($this->app);

    expect(fn () => $provider->boot())->not->toThrow(Throwable::class);
});
