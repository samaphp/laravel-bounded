<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Console\Generators;

use Illuminate\Console\GeneratorCommand;

final class MakeJobCommand extends GeneratorCommand
{
    protected $name = 'make:job';

    protected $description = 'Create a new queued job (Bounded layout: app/Jobs/{Domain}/{Name} — thin transport, delegate to a service)';

    protected $type = 'Job';

    protected function getStub(): string
    {
        return __DIR__ . '/../../stubs/job.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\Jobs';
    }
}
