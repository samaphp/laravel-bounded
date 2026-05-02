<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Console\Generators;

use Illuminate\Console\GeneratorCommand;

final class MakeJobCommand extends GeneratorCommand
{
    use RequiresDomainPath;

    // Renamed from `make:job` to avoid silently overriding Laravel's core
    // `make:job` command. Other Bounded generators (action/service/repository/
    // integration) don't collide with core; only this one needed the prefix.
    protected $name = 'make:bounded-job';

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
