<?php

declare(strict_types=1);

use Samaphp\LaravelBounded\Validators\ProblemKind;
use Samaphp\LaravelBounded\Validators\ZonePartitionValidator;

it('passes when zones partition cleanly', function () {
    config()->set('bounded.zones', [
        'logic' => ['app/Services'],
        'framework_bridge' => ['app/Providers'],
        'repository' => ['app/Repositories'],
    ]);

    $result = $this->app->make(ZonePartitionValidator::class)->validate();

    expect($result->passed())->toBeTrue();
});

it('emits a ZoneOverlap Problem when a path appears in multiple zones', function () {
    config()->set('bounded.zones', [
        'logic' => ['app/Services', 'app/Repositories'],
        'repository' => ['app/Repositories'],
    ]);

    $result = $this->app->make(ZonePartitionValidator::class)->validate();

    expect($result->passed())->toBeFalse();
    expect($result->problems())->toHaveCount(1);
    expect($result->violations())->toBeEmpty();

    $problem = $result->problems()[0];
    expect($problem->kind)->toBe(ProblemKind::ZoneOverlap);
    expect($problem->context)->toBe('config/bounded.php');
    expect($problem->message)
        ->toContain('app/Repositories')
        ->toContain('logic')
        ->toContain('repository');
});

it('reports multiple cross-zone duplicates separately', function () {
    config()->set('bounded.zones', [
        'logic' => ['app/Services', 'app/A', 'app/B'],
        'framework_bridge' => ['app/A'],
        'repository' => ['app/B'],
    ]);

    $result = $this->app->make(ZonePartitionValidator::class)->validate();

    expect($result->passed())->toBeFalse();
    expect($result->problems())->toHaveCount(2);
});

it('passes when zones config is empty', function () {
    config()->set('bounded.zones', []);

    $result = $this->app->make(ZonePartitionValidator::class)->validate();

    expect($result->passed())->toBeTrue();
});

it('exposes its name on both the validator and the result', function () {
    $validator = $this->app->make(ZonePartitionValidator::class);

    expect($validator->name())->toBe('ZonePartition');
    expect($validator->validate()->validator)->toBe('ZonePartition');
});
