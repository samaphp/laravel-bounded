<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Console\Generators;

use Illuminate\Console\GeneratorCommand;

final class MakeServiceCommand extends GeneratorCommand
{
    protected $name = 'make:service';

    protected $description = 'Create a new service class (Bounded layout: app/Services/{Domain}/{Name})';

    protected $type = 'Service';

    protected function getStub(): string
    {
        return __DIR__ . '/../../stubs/service.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\Services';
    }
}
