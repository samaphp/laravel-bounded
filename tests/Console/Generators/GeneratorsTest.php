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

it('make:action creates a final invokable controller under Http/Controllers/{Domain}', function () {
    $className = 'ShowGen' . uniqid();
    $name = 'Order/' . $className;
    $expected = $this->app->basePath('app/Http/Controllers/' . $name . '.php');
    $this->createdFiles[] = $expected;

    $this->artisan('make:action', ['name' => $name])->assertExitCode(0);

    expect(is_file($expected))->toBeTrue();
    $contents = file_get_contents($expected);
    expect($contents)
        ->toContain('final class ' . $className)
        ->toContain('public function __invoke')
        ->toContain('namespace App\\Http\\Controllers\\Order');
});

it('make:service creates a final service under Services/{Domain}', function () {
    $className = 'CreateOrderGen' . uniqid();
    $name = 'Order/' . $className;
    $expected = $this->app->basePath('app/Services/' . $name . '.php');
    $this->createdFiles[] = $expected;

    $this->artisan('make:service', ['name' => $name])->assertExitCode(0);

    expect(is_file($expected))->toBeTrue();
    $contents = file_get_contents($expected);
    expect($contents)
        ->toContain('final class ' . $className)
        ->toContain('namespace App\\Services\\Order');
});

it('make:repository creates a final repository under Repositories/{Domain}', function () {
    $className = 'OrderRepositoryGen' . uniqid();
    $name = 'Order/' . $className;
    $expected = $this->app->basePath('app/Repositories/' . $name . '.php');
    $this->createdFiles[] = $expected;

    $this->artisan('make:repository', ['name' => $name])->assertExitCode(0);

    expect(is_file($expected))->toBeTrue();
    $contents = file_get_contents($expected);
    expect($contents)
        ->toContain('final class ' . $className)
        ->toContain('namespace App\\Repositories\\Order');
});

it('make:integration creates a final integration under Integrations/{Vendor}', function () {
    $className = 'PaymentGatewayGen' . uniqid();
    $name = 'Stripe/' . $className;
    $expected = $this->app->basePath('app/Integrations/' . $name . '.php');
    $this->createdFiles[] = $expected;

    $this->artisan('make:integration', ['name' => $name])->assertExitCode(0);

    expect(is_file($expected))->toBeTrue();
    $contents = file_get_contents($expected);
    expect($contents)
        ->toContain('namespace App\\Integrations\\Stripe')
        ->toContain('final class ' . $className);
});

it('make:bounded-job creates a final queueable job under Jobs/{Domain}', function () {
    $className = 'SendOrderEmailGen' . uniqid();
    $name = 'Email/' . $className;
    $expected = $this->app->basePath('app/Jobs/' . $name . '.php');
    $this->createdFiles[] = $expected;

    $this->artisan('make:bounded-job', ['name' => $name])->assertExitCode(0);

    expect(is_file($expected))->toBeTrue();
    $contents = file_get_contents($expected);
    expect($contents)
        ->toContain('final class ' . $className)
        ->toContain('implements ShouldQueue')
        ->toContain('public function handle')
        ->toContain('namespace App\\Jobs\\Email');
});

it('rejects bare names without a Domain/Vendor segment', function () {
    foreach (['make:action', 'make:service', 'make:repository', 'make:integration', 'make:bounded-job'] as $command) {
        $this->artisan($command, ['name' => 'BareName'])->assertExitCode(1);
    }
});
