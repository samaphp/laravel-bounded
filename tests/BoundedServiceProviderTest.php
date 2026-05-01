<?php

declare(strict_types=1);

use Samaphp\LaravelBounded\BoundedServiceProvider;

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
