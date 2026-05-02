<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Console\Generators;

use Illuminate\Console\GeneratorCommand;

final class MakeActionCommand extends GeneratorCommand
{
    protected $name = 'make:action';

    protected $description = 'Create a new invokable action controller (Bounded layout: app/Http/Controllers/{Domain}/{Name})';

    protected $type = 'Action';

    protected function getStub(): string
    {
        return __DIR__ . '/../../stubs/action.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\Http\\Controllers';
    }
}
