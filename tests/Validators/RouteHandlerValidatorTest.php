<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Samaphp\LaravelBounded\Validators\RouteHandlerValidator;

beforeEach(function (): void {
    $this->fixturePath = sys_get_temp_dir() . '/laravel-bounded-rh-' . uniqid();
    mkdir($this->fixturePath . '/routes', recursive: true);
});

afterEach(function (): void {
    if (isset($this->fixturePath) && is_dir($this->fixturePath)) {
        File::deleteDirectory($this->fixturePath);
    }
});

function writeRoutes(string $base, string $contents, string $name = 'web.php'): void
{
    file_put_contents($base . '/routes/' . $name, $contents);
}

it('passes silently when routes/ does not exist', function (): void {
    File::deleteDirectory($this->fixturePath . '/routes');

    $result = (new RouteHandlerValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('allows a class-string handler via ::class', function (): void {
    writeRoutes($this->fixturePath, <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        Route::get('/orders/{id}', \App\Http\Controllers\Order\Show::class);
        PHP);

    $result = (new RouteHandlerValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('allows a fully-qualified string class name', function (): void {
    writeRoutes($this->fixturePath, <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        Route::get('/orders', 'App\\Http\\Controllers\\Order\\Index');
        PHP);

    $result = (new RouteHandlerValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('allows Route::redirect / view / permanentRedirect (no handler)', function (): void {
    writeRoutes($this->fixturePath, <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        Route::redirect('/old', '/new');
        Route::permanentRedirect('/older', '/new');
        Route::view('/static', 'static-page');
        PHP);

    $result = (new RouteHandlerValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('flags a closure handler', function (): void {
    writeRoutes($this->fixturePath, <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        Route::get('/x', function () {
            return view('welcome');
        });
        PHP);

    $result = (new RouteHandlerValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    expect($result->violations())->toHaveCount(1);
    expect($result->violations()[0]->message)->toContain('invokable controller class string');
});

it('flags an arrow-function handler', function (): void {
    writeRoutes($this->fixturePath, <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        Route::get('/x', fn () => view('welcome'));
        PHP);

    $result = (new RouteHandlerValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
});

it('flags a [Controller::class, method] tuple', function (): void {
    writeRoutes($this->fixturePath, <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        Route::get('/x', [\App\Http\Controllers\Order\Show::class, 'show']);
        PHP);

    $result = (new RouteHandlerValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    expect($result->violations()[0]->message)->toContain('No closures, no [Controller::class');
});

it('handles chained Route::middleware(...)->get(...) form', function (): void {
    writeRoutes($this->fixturePath, <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        Route::middleware('auth')->get('/x', \App\Http\Controllers\Order\Show::class);
        Route::middleware('auth')->post('/y', function () {});
        PHP);

    $result = (new RouteHandlerValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    expect($result->violations())->toHaveCount(1);
});

it('handles Route::match (handler is third arg)', function (): void {
    writeRoutes($this->fixturePath, <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        Route::match(['get', 'post'], '/x', \App\Http\Controllers\Order\Show::class);
        Route::match(['get', 'post'], '/y', fn () => null);
        PHP);

    $result = (new RouteHandlerValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    expect($result->violations())->toHaveCount(1);
});

it('flags a non-namespaced string handler', function (): void {
    writeRoutes($this->fixturePath, <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        Route::get('/x', 'Show');
        PHP);

    $result = (new RouteHandlerValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
});

it('flags the pre-Laravel-8 Controller@method string form', function (): void {
    writeRoutes($this->fixturePath, <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        Route::get('/x', 'App\\Http\\Controllers\\Order\\Show@show');
        PHP);

    $result = (new RouteHandlerValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
});

it('exposes its name on validator and result', function (): void {
    $validator = new RouteHandlerValidator($this->fixturePath);

    expect($validator->name())->toBe('RouteHandler');
    expect($validator->validate()->validator)->toBe('RouteHandler');
});
