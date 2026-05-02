<?php

declare(strict_types=1);

it('arch:check is registered', function () {
    expect(\Illuminate\Support\Facades\Artisan::all())
        ->toHaveKey('arch:check');
});

it('arch:check has the expected signature with --skip-coverage option', function () {
    $command = \Illuminate\Support\Facades\Artisan::all()['arch:check'];
    $definition = $command->getDefinition();

    expect($definition->hasOption('skip-coverage'))->toBeTrue();
});
