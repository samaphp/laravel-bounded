<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Console\Generators;

use Illuminate\Console\GeneratorCommand;

final class MakeIntegrationCommand extends GeneratorCommand
{
    protected $name = 'make:integration';

    protected $description = 'Create a new third-party SDK wrapper (Bounded layout: app/Integrations/{Vendor}/{Name})';

    protected $type = 'Integration';

    protected function getStub(): string
    {
        return __DIR__ . '/../../stubs/integration.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\Integrations';
    }
}
