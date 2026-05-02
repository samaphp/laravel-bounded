<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Console\Generators;

use Illuminate\Console\GeneratorCommand;

final class MakeRepositoryCommand extends GeneratorCommand
{
    protected $name = 'make:repository';

    protected $description = 'Create a new repository class (Bounded layout: app/Repositories/{Name} — Eloquent allowed in this zone)';

    protected $type = 'Repository';

    protected function getStub(): string
    {
        return __DIR__ . '/../../stubs/repository.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\Repositories';
    }
}
