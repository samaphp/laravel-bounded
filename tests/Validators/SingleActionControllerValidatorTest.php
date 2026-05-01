<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Samaphp\LaravelBounded\Validators\ProblemKind;
use Samaphp\LaravelBounded\Validators\SingleActionControllerValidator;

beforeEach(function () {
    $this->fixturePath = sys_get_temp_dir() . '/laravel-bounded-sac-' . uniqid();
    mkdir($this->fixturePath);

    $this->writeFile = function (string $relativePath, string $contents): void {
        $fullPath = $this->fixturePath . '/' . ltrim($relativePath, '/');
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, recursive: true);
        }
        file_put_contents($fullPath, $contents);
    };
});

afterEach(function () {
    if (isset($this->fixturePath) && is_dir($this->fixturePath)) {
        File::deleteDirectory($this->fixturePath);
    }
});

it('passes when controller is final, has __invoke, and has no Controller suffix', function () {
    ($this->writeFile)('app/Http/Controllers/Order/Show.php', <<<'PHP'
        <?php
        namespace App\Http\Controllers\Order;
        final class Show
        {
            public function __invoke() { return 'ok'; }
        }
        PHP);

    $result = (new SingleActionControllerValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('emits a Violation when the class is not final', function () {
    ($this->writeFile)('app/Http/Controllers/Show.php', <<<'PHP'
        <?php
        class Show
        {
            public function __invoke() {}
        }
        PHP);

    $result = (new SingleActionControllerValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    $messages = array_map(static fn ($v) => $v->message, $result->violations());
    expect(implode("\n", $messages))->toContain('must be `final`');
});

it('emits a Violation when the class name has a Controller suffix', function () {
    ($this->writeFile)('app/Http/Controllers/OrderController.php', <<<'PHP'
        <?php
        final class OrderController
        {
            public function __invoke() {}
        }
        PHP);

    $result = (new SingleActionControllerValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    $messages = array_map(static fn ($v) => $v->message, $result->violations());
    expect(implode("\n", $messages))->toContain('must not have a `Controller` suffix');
});

it('emits a Violation when the class lacks an __invoke method', function () {
    ($this->writeFile)('app/Http/Controllers/Show.php', <<<'PHP'
        <?php
        final class Show
        {
            public function index() {}
        }
        PHP);

    $result = (new SingleActionControllerValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    $messages = array_map(static fn ($v) => $v->message, $result->violations());
    expect(implode("\n", $messages))->toContain('must define an `__invoke` method');
});

it('emits a Violation when the class has additional public methods beyond __invoke', function () {
    ($this->writeFile)('app/Http/Controllers/Show.php', <<<'PHP'
        <?php
        final class Show
        {
            public function __invoke() {}
            public function helperMethod() {}
        }
        PHP);

    $result = (new SingleActionControllerValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    $messages = array_map(static fn ($v) => $v->message, $result->violations());
    expect(implode("\n", $messages))->toContain('helperMethod');
});

it('allows __construct as an additional public method', function () {
    ($this->writeFile)('app/Http/Controllers/Show.php', <<<'PHP'
        <?php
        final class Show
        {
            public function __construct(private readonly string $service) {}
            public function __invoke() {}
        }
        PHP);

    $result = (new SingleActionControllerValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('skips abstract classes', function () {
    ($this->writeFile)('app/Http/Controllers/AbstractShow.php', <<<'PHP'
        <?php
        abstract class AbstractShow
        {
            abstract public function __invoke();
        }
        PHP);

    $result = (new SingleActionControllerValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('emits ScanPathMissing Problem when controllers dir does not exist', function () {
    $result = (new SingleActionControllerValidator($this->fixturePath))->validate();

    expect($result->problems())->toHaveCount(1);
    expect($result->problems()[0]->kind)->toBe(ProblemKind::ScanPathMissing);
    expect($result->problems()[0]->context)->toBe('app/Http/Controllers');
});

it('emits ScanPathEmpty Problem when controllers dir is empty', function () {
    mkdir($this->fixturePath . '/app/Http/Controllers', recursive: true);

    $result = (new SingleActionControllerValidator($this->fixturePath))->validate();

    expect($result->problems())->toHaveCount(1);
    expect($result->problems()[0]->kind)->toBe(ProblemKind::ScanPathEmpty);
});

it('skips when controllers path is listed in ignoredScanPaths', function () {
    // No controllers dir created. Without ignore, this would emit ScanPathMissing.
    $result = (new SingleActionControllerValidator(
        $this->fixturePath,
        ignoredScanPaths: ['app/Http/Controllers'],
    ))->validate();

    expect($result->passed())->toBeTrue();
    expect($result->problems())->toBeEmpty();
});

it('exposes its name on both the validator and the result', function () {
    $validator = new SingleActionControllerValidator($this->fixturePath);

    expect($validator->name())->toBe('SingleActionController');
    expect($validator->validate()->validator)->toBe('SingleActionController');
});
