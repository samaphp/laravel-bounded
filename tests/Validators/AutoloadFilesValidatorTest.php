<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Samaphp\LaravelBounded\Validators\AutoloadFilesValidator;
use Samaphp\LaravelBounded\Validators\ProblemKind;

beforeEach(function (): void {
    $this->fixturePath = sys_get_temp_dir() . '/laravel-bounded-af-' . uniqid();
    mkdir($this->fixturePath);
});

afterEach(function (): void {
    if (isset($this->fixturePath) && is_dir($this->fixturePath)) {
        File::deleteDirectory($this->fixturePath);
    }
});

it('passes when composer.json has no autoload.files entry', function (): void {
    file_put_contents(
        $this->fixturePath . '/composer.json',
        json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]),
    );

    $result = (new AutoloadFilesValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('emits AutoloadFilesPresent when composer.json declares autoload.files', function (): void {
    file_put_contents(
        $this->fixturePath . '/composer.json',
        json_encode([
            'autoload' => [
                'psr-4' => ['App\\' => 'app/'],
                'files' => ['app/helpers.php'],
            ],
        ]),
    );

    $result = (new AutoloadFilesValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    expect($result->problems())->toHaveCount(1);
    expect($result->problems()[0]->kind)->toBe(ProblemKind::AutoloadFilesPresent);
    expect($result->problems()[0]->message)
        ->toContain('autoload.files')
        ->toContain('app/helpers.php');
});

it('also flags autoload-dev.files', function (): void {
    file_put_contents(
        $this->fixturePath . '/composer.json',
        json_encode([
            'autoload-dev' => [
                'files' => ['tests/dev_helpers.php'],
            ],
        ]),
    );

    $result = (new AutoloadFilesValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    expect($result->problems()[0]->message)->toContain('autoload-dev.files');
});

it('passes silently when composer.json is missing', function (): void {
    $result = (new AutoloadFilesValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('passes silently when composer.json is unparseable', function (): void {
    file_put_contents($this->fixturePath . '/composer.json', '{ broken');

    $result = (new AutoloadFilesValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('exposes its name on validator and result', function (): void {
    $validator = new AutoloadFilesValidator($this->fixturePath);

    expect($validator->name())->toBe('AutoloadFiles');
    expect($validator->validate()->validator)->toBe('AutoloadFiles');
});
