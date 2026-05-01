<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Samaphp\LaravelBounded\Validators\NoModelHooksValidator;

beforeEach(function () {
    $this->fixturePath = sys_get_temp_dir() . '/laravel-bounded-nmh-' . uniqid();
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

it('passes when app/Models does not exist', function () {
    $result = (new NoModelHooksValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('passes when app/Models is empty', function () {
    mkdir($this->fixturePath . '/app/Models', recursive: true);

    $result = (new NoModelHooksValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('passes for a model with no boot or booted overrides', function () {
    ($this->writeFile)('app/Models/Order.php', <<<'PHP'
        <?php
        namespace App\Models;
        use Illuminate\Database\Eloquent\Model;
        final class Order extends Model
        {
            protected $fillable = ['customer_name'];
        }
        PHP);

    $result = (new NoModelHooksValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('emits a Violation when a model overrides boot()', function () {
    ($this->writeFile)('app/Models/Order.php', <<<'PHP'
        <?php
        namespace App\Models;
        use Illuminate\Database\Eloquent\Model;
        final class Order extends Model
        {
            protected static function boot()
            {
                parent::boot();
            }
        }
        PHP);

    $result = (new NoModelHooksValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    expect($result->violations())->toHaveCount(1);
    expect($result->violations()[0]->message)->toContain('boot()');
    expect($result->violations()[0]->file)->toBe('app/Models/Order.php');
});

it('emits a Violation when a model overrides booted()', function () {
    ($this->writeFile)('app/Models/User.php', <<<'PHP'
        <?php
        namespace App\Models;
        use Illuminate\Database\Eloquent\Model;
        final class User extends Model
        {
            protected static function booted()
            {
                static::saving(fn ($user) => null);
            }
        }
        PHP);

    $result = (new NoModelHooksValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    expect($result->violations())->toHaveCount(1);
    expect($result->violations()[0]->message)->toContain('booted()');
});

it('emits two Violations when a model overrides both boot() and booted()', function () {
    ($this->writeFile)('app/Models/Both.php', <<<'PHP'
        <?php
        namespace App\Models;
        use Illuminate\Database\Eloquent\Model;
        final class Both extends Model
        {
            protected static function boot() { parent::boot(); }
            protected static function booted() {}
        }
        PHP);

    $result = (new NoModelHooksValidator($this->fixturePath))->validate();

    expect($result->violations())->toHaveCount(2);
});

it('skips when app/Models is listed in ignoredScanPaths', function () {
    ($this->writeFile)('app/Models/Order.php', <<<'PHP'
        <?php
        namespace App\Models;
        use Illuminate\Database\Eloquent\Model;
        final class Order extends Model
        {
            protected static function boot() { parent::boot(); }
        }
        PHP);

    $result = (new NoModelHooksValidator(
        $this->fixturePath,
        ignoredScanPaths: ['app/Models'],
    ))->validate();

    expect($result->passed())->toBeTrue();
});

it('exposes its name on both the validator and the result', function () {
    $validator = new NoModelHooksValidator($this->fixturePath);

    expect($validator->name())->toBe('NoModelHooks');
    expect($validator->validate()->validator)->toBe('NoModelHooks');
});
