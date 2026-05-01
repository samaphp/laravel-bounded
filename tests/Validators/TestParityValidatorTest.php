<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Samaphp\LaravelBounded\Validators\ProblemKind;
use Samaphp\LaravelBounded\Validators\TestParityValidator;

beforeEach(function () {
    $this->fixturePath = sys_get_temp_dir() . '/laravel-bounded-tp-' . uniqid();
    mkdir($this->fixturePath);
});

afterEach(function () {
    if (isset($this->fixturePath) && is_dir($this->fixturePath)) {
        File::deleteDirectory($this->fixturePath);
    }
});

it('passes when each scanned file has a matching Feature test', function () {
    foreach (['app/Http/Controllers/Order', 'app/Console/Commands', 'app/Jobs/Email'] as $dir) {
        mkdir($this->fixturePath . '/' . $dir, recursive: true);
    }
    foreach (['tests/Feature/Http/Controllers/Order', 'tests/Feature/Console/Commands', 'tests/Feature/Jobs/Email'] as $dir) {
        mkdir($this->fixturePath . '/' . $dir, recursive: true);
    }

    file_put_contents($this->fixturePath . '/app/Http/Controllers/Order/Show.php', '<?php class Show {}');
    file_put_contents($this->fixturePath . '/app/Console/Commands/SendReports.php', '<?php class SendReports {}');
    file_put_contents($this->fixturePath . '/app/Jobs/Email/SendOrderEmail.php', '<?php class SendOrderEmail {}');
    file_put_contents($this->fixturePath . '/tests/Feature/Http/Controllers/Order/ShowTest.php', '<?php');
    file_put_contents($this->fixturePath . '/tests/Feature/Console/Commands/SendReportsTest.php', '<?php');
    file_put_contents($this->fixturePath . '/tests/Feature/Jobs/Email/SendOrderEmailTest.php', '<?php');

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('passes when matching test is under tests/Unit instead of Feature', function () {
    foreach (['app/Http/Controllers', 'app/Console/Commands', 'app/Jobs'] as $dir) {
        mkdir($this->fixturePath . '/' . $dir, recursive: true);
    }
    file_put_contents($this->fixturePath . '/app/Http/Controllers/Home.php', '<?php class Home {}');
    file_put_contents($this->fixturePath . '/app/Console/Commands/Foo.php', '<?php class Foo {}');
    file_put_contents($this->fixturePath . '/app/Jobs/Bar.php', '<?php class Bar {}');

    mkdir($this->fixturePath . '/tests/Unit/Http/Controllers', recursive: true);
    mkdir($this->fixturePath . '/tests/Unit/Console/Commands', recursive: true);
    mkdir($this->fixturePath . '/tests/Unit/Jobs', recursive: true);
    file_put_contents($this->fixturePath . '/tests/Unit/Http/Controllers/HomeTest.php', '<?php');
    file_put_contents($this->fixturePath . '/tests/Unit/Console/Commands/FooTest.php', '<?php');
    file_put_contents($this->fixturePath . '/tests/Unit/Jobs/BarTest.php', '<?php');

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('emits a Violation for each file missing its mirror test', function () {
    foreach (['app/Http/Controllers', 'app/Console/Commands', 'app/Jobs'] as $dir) {
        mkdir($this->fixturePath . '/' . $dir, recursive: true);
    }
    file_put_contents($this->fixturePath . '/app/Http/Controllers/Unmatched.php', '<?php class Unmatched {}');
    file_put_contents($this->fixturePath . '/app/Console/Commands/RunIt.php', '<?php class RunIt {}');
    file_put_contents($this->fixturePath . '/app/Jobs/JobOne.php', '<?php class JobOne {}');

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    expect($result->violations())->toHaveCount(3);
    expect($result->problems())->toBeEmpty();

    $files = array_map(static fn ($v) => $v->file, $result->violations());
    expect($files)
        ->toContain('app/Http/Controllers/Unmatched.php')
        ->toContain('app/Console/Commands/RunIt.php')
        ->toContain('app/Jobs/JobOne.php');
});

it('emits ScanPathMissing Problem when a scan dir does not exist', function () {
    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    expect($result->violations())->toBeEmpty();
    expect($result->problems())->toHaveCount(3);

    foreach ($result->problems() as $problem) {
        expect($problem->kind)->toBe(ProblemKind::ScanPathMissing);
        expect($problem->message)->toContain('does not exist');
    }
});

it('emits ScanPathEmpty Problem when a scan dir exists but has no .php files', function () {
    foreach (['app/Http/Controllers', 'app/Console/Commands', 'app/Jobs'] as $dir) {
        mkdir($this->fixturePath . '/' . $dir, recursive: true);
    }

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    expect($result->violations())->toBeEmpty();
    expect($result->problems())->toHaveCount(3);

    foreach ($result->problems() as $problem) {
        expect($problem->kind)->toBe(ProblemKind::ScanPathEmpty);
        expect($problem->message)
            ->toContain('No files matched')
            ->toContain('ignore');
    }
});

it('distinguishes missing-path from empty-path with different kinds and messages', function () {
    mkdir($this->fixturePath . '/app/Http/Controllers', recursive: true);
    // Don't create app/Console/Commands or app/Jobs

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->problems())->toHaveCount(3);

    $kinds = array_map(static fn ($p) => $p->kind, $result->problems());
    expect($kinds)
        ->toContain(ProblemKind::ScanPathEmpty)
        ->toContain(ProblemKind::ScanPathMissing);

    $emptyProblems = array_values(array_filter(
        $result->problems(),
        static fn ($p) => $p->kind === ProblemKind::ScanPathEmpty,
    ));
    $missingProblems = array_values(array_filter(
        $result->problems(),
        static fn ($p) => $p->kind === ProblemKind::ScanPathMissing,
    ));

    expect($emptyProblems[0]->message)->toContain('No files matched');
    expect($missingProblems[0]->message)->toContain('does not exist');
});

it('skips scan paths listed in ignoredScanPaths', function () {
    mkdir($this->fixturePath . '/app/Http/Controllers', recursive: true);
    mkdir($this->fixturePath . '/tests/Feature/Http/Controllers', recursive: true);
    file_put_contents($this->fixturePath . '/app/Http/Controllers/Home.php', '<?php class Home {}');
    file_put_contents($this->fixturePath . '/tests/Feature/Http/Controllers/HomeTest.php', '<?php');
    // Don't create app/Console/Commands or app/Jobs — they would emit ScanPathMissing without the ignore.

    $result = (new TestParityValidator(
        $this->fixturePath,
        ignoredScanPaths: ['app/Console/Commands', 'app/Jobs'],
    ))->validate();

    expect($result->passed())->toBeTrue();
});

it('exposes its name on both the validator and the result', function () {
    $validator = new TestParityValidator($this->fixturePath);

    expect($validator->name())->toBe('TestParity');
    expect($validator->validate()->validator)->toBe('TestParity');
});
