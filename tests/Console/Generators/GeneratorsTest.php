<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

afterEach(function () {
    foreach ($this->createdFiles ?? [] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

function generatorPath(\Tests\TestCase $test, string $relativePath): string
{
    return $test->app->basePath($relativePath);
}

it('make:action is registered', function () {
    expect(\Illuminate\Support\Facades\Artisan::all())->toHaveKey('make:action');
});

it('make:service is registered', function () {
    expect(\Illuminate\Support\Facades\Artisan::all())->toHaveKey('make:service');
});

it('make:repository is registered', function () {
    expect(\Illuminate\Support\Facades\Artisan::all())->toHaveKey('make:repository');
});

it('make:integration is registered', function () {
    expect(\Illuminate\Support\Facades\Artisan::all())->toHaveKey('make:integration');
});

it('make:bounded-job is registered', function () {
    expect(\Illuminate\Support\Facades\Artisan::all())->toHaveKey('make:bounded-job');
});

it('make:action creates a final invokable controller in app/Http/Controllers', function () {
    $name = 'TestActionGen' . uniqid();
    $expected = $this->app->basePath('app/Http/Controllers/' . $name . '.php');
    $this->createdFiles[] = $expected;

    $this->artisan('make:action', ['name' => $name])->assertExitCode(0);

    expect(is_file($expected))->toBeTrue();
    $contents = file_get_contents($expected);
    expect($contents)
        ->toContain('final class ' . $name)
        ->toContain('public function __invoke')
        ->toContain('namespace App\\Http\\Controllers');
});

it('make:service creates a final service in app/Services', function () {
    $name = 'TestServiceGen' . uniqid();
    $expected = $this->app->basePath('app/Services/' . $name . '.php');
    $this->createdFiles[] = $expected;

    $this->artisan('make:service', ['name' => $name])->assertExitCode(0);

    expect(is_file($expected))->toBeTrue();
    $contents = file_get_contents($expected);
    expect($contents)
        ->toContain('final class ' . $name)
        ->toContain('namespace App\\Services');
});

it('make:repository creates a final repository in app/Repositories', function () {
    $name = 'TestRepoGen' . uniqid();
    $expected = $this->app->basePath('app/Repositories/' . $name . '.php');
    $this->createdFiles[] = $expected;

    $this->artisan('make:repository', ['name' => $name])->assertExitCode(0);

    expect(is_file($expected))->toBeTrue();
    $contents = file_get_contents($expected);
    expect($contents)
        ->toContain('final class ' . $name)
        ->toContain('namespace App\\Repositories');
});

it('make:integration creates a final integration with vendor namespace', function () {
    $name = 'Stripe/PaymentGatewayGen' . uniqid();
    $className = 'PaymentGatewayGen' . substr($name, strpos($name, 'Gen') + 3);
    $expected = $this->app->basePath('app/Integrations/' . str_replace('/', '/', $name) . '.php');
    $this->createdFiles[] = $expected;

    $this->artisan('make:integration', ['name' => $name])->assertExitCode(0);

    expect(is_file($expected))->toBeTrue();
    $contents = file_get_contents($expected);
    expect($contents)
        ->toContain('namespace App\\Integrations\\Stripe')
        ->toContain('final class ');
});

it('make:bounded-job creates a final queueable job in app/Jobs', function () {
    $name = 'TestJobGen' . uniqid();
    $expected = $this->app->basePath('app/Jobs/' . $name . '.php');
    $this->createdFiles[] = $expected;

    $this->artisan('make:bounded-job', ['name' => $name])->assertExitCode(0);

    expect(is_file($expected))->toBeTrue();
    $contents = file_get_contents($expected);
    expect($contents)
        ->toContain('final class ' . $name)
        ->toContain('implements ShouldQueue')
        ->toContain('public function handle')
        ->toContain('namespace App\\Jobs');
});

it('make:action supports subdirectory namespace', function () {
    $name = 'Order/ShowGen' . uniqid();
    $className = explode('/', $name)[1];
    $expected = $this->app->basePath('app/Http/Controllers/' . $name . '.php');
    $this->createdFiles[] = $expected;

    $this->artisan('make:action', ['name' => $name])->assertExitCode(0);

    expect(is_file($expected))->toBeTrue();
    $contents = file_get_contents($expected);
    expect($contents)
        ->toContain('namespace App\\Http\\Controllers\\Order')
        ->toContain('final class ' . $className);
});
