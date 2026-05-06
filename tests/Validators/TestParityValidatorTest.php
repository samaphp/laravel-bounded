<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
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

/**
 * Helper: write a PHP class file. `$source` is full file contents.
 */
function writeFile(string $path, string $source): void
{
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), recursive: true);
    }
    file_put_contents($path, $source);
}

/**
 * Helper: minimal command class — extends Illuminate\Console\Command via use-import.
 */
function commandSource(string $namespace, string $className, bool $abstract = false): string
{
    $abstractKw = $abstract ? 'abstract ' : 'final ';

    return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Console\Command;

{$abstractKw}class {$className} extends Command
{
}
PHP;
}

/**
 * Helper: minimal job class — implements ShouldQueue via use-import.
 */
function jobSource(string $namespace, string $className, bool $abstract = false): string
{
    $abstractKw = $abstract ? 'abstract ' : 'final ';

    return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Contracts\Queue\ShouldQueue;

{$abstractKw}class {$className} implements ShouldQueue
{
}
PHP;
}

/**
 * Helper: a class with a custom parent (referenced by short name with use).
 */
function subclassSource(string $namespace, string $className, string $parentFqn): string
{
    $parentShort = ltrim(strrchr('\\' . $parentFqn, '\\'), '\\');

    return <<<PHP
<?php

namespace {$namespace};

use {$parentFqn};

final class {$className} extends {$parentShort}
{
}
PHP;
}

it('stays silent when no commands or jobs exist anywhere in app/', function () {
    mkdir($this->fixturePath . '/app/Http/Controllers', recursive: true);

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
    expect($result->problems())->toBeEmpty();
    expect($result->violations())->toBeEmpty();
});

it('stays silent when app/ does not exist at all', function () {
    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('passes when controllers, commands, and jobs each have a Feature mirror test', function () {
    writeFile(
        $this->fixturePath . '/app/Http/Controllers/Order/Show.php',
        '<?php namespace App\Http\Controllers\Order; final class Show {}',
    );
    writeFile(
        $this->fixturePath . '/app/Console/Commands/SendReports.php',
        commandSource('App\Console\Commands', 'SendReports'),
    );
    writeFile(
        $this->fixturePath . '/app/Jobs/Email/SendOrderEmail.php',
        jobSource('App\Jobs\Email', 'SendOrderEmail'),
    );
    writeFile($this->fixturePath . '/tests/Feature/Http/Controllers/Order/ShowTest.php', '<?php');
    writeFile($this->fixturePath . '/tests/Feature/Console/Commands/SendReportsTest.php', '<?php');
    writeFile($this->fixturePath . '/tests/Feature/Jobs/Email/SendOrderEmailTest.php', '<?php');

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('accepts mirror tests under tests/Unit instead of tests/Feature', function () {
    writeFile(
        $this->fixturePath . '/app/Http/Controllers/Home.php',
        '<?php namespace App\Http\Controllers; final class Home {}',
    );
    writeFile(
        $this->fixturePath . '/app/Console/Commands/Foo.php',
        commandSource('App\Console\Commands', 'Foo'),
    );
    writeFile(
        $this->fixturePath . '/app/Jobs/Bar.php',
        jobSource('App\Jobs', 'Bar'),
    );
    writeFile($this->fixturePath . '/tests/Unit/Http/Controllers/HomeTest.php', '<?php');
    writeFile($this->fixturePath . '/tests/Unit/Console/Commands/FooTest.php', '<?php');
    writeFile($this->fixturePath . '/tests/Unit/Jobs/BarTest.php', '<?php');

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('emits a Violation for each entrypoint missing its mirror test', function () {
    writeFile(
        $this->fixturePath . '/app/Http/Controllers/Unmatched.php',
        '<?php namespace App\Http\Controllers; final class Unmatched {}',
    );
    writeFile(
        $this->fixturePath . '/app/Console/Commands/RunIt.php',
        commandSource('App\Console\Commands', 'RunIt'),
    );
    writeFile(
        $this->fixturePath . '/app/Jobs/JobOne.php',
        jobSource('App\Jobs', 'JobOne'),
    );

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    expect($result->problems())->toBeEmpty();
    expect($result->violations())->toHaveCount(3);

    $files = array_map(static fn ($v) => $v->file, $result->violations());
    expect($files)
        ->toContain('app/Http/Controllers/Unmatched.php')
        ->toContain('app/Console/Commands/RunIt.php')
        ->toContain('app/Jobs/JobOne.php');

    foreach ($result->violations() as $violation) {
        expect($violation->message)->toContain('No matching test found');
    }
});

it('flags a misplaced command outside app/Console/Commands', function () {
    writeFile(
        $this->fixturePath . '/app/Foo/MyCmd.php',
        commandSource('App\Foo', 'MyCmd'),
    );

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    expect($result->violations())->toHaveCount(1);

    $violation = $result->violations()[0];
    expect($violation->file)->toBe('app/Foo/MyCmd.php');
    expect($violation->message)
        ->toContain('Console command')
        ->toContain('App\Foo\MyCmd')
        ->toContain('app/Console/Commands');
});

it('flags a misplaced job outside app/Jobs', function () {
    writeFile(
        $this->fixturePath . '/app/Workflows/SendEmail.php',
        jobSource('App\Workflows', 'SendEmail'),
    );

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    expect($result->violations())->toHaveCount(1);

    $violation = $result->violations()[0];
    expect($violation->file)->toBe('app/Workflows/SendEmail.php');
    expect($violation->message)
        ->toContain('Job')
        ->toContain('App\Workflows\SendEmail')
        ->toContain('app/Jobs');
});

it('detects commands transitively through an in-project base class', function () {
    writeFile(
        $this->fixturePath . '/app/Console/Commands/BaseCommand.php',
        commandSource('App\Console\Commands', 'BaseCommand', abstract: true),
    );
    writeFile(
        $this->fixturePath . '/app/Console/Commands/ConcreteCmd.php',
        subclassSource('App\Console\Commands', 'ConcreteCmd', 'App\Console\Commands\BaseCommand'),
    );

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->violations())->toHaveCount(1);
    expect($result->violations()[0]->file)->toBe('app/Console/Commands/ConcreteCmd.php');
    expect($result->violations()[0]->message)->toContain('No matching test found');
});

it('detects jobs transitively through an in-project base class', function () {
    writeFile(
        $this->fixturePath . '/app/Jobs/BaseJob.php',
        jobSource('App\Jobs', 'BaseJob', abstract: true),
    );
    writeFile(
        $this->fixturePath . '/app/Jobs/ConcreteJob.php',
        subclassSource('App\Jobs', 'ConcreteJob', 'App\Jobs\BaseJob'),
    );

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->violations())->toHaveCount(1);
    expect($result->violations()[0]->file)->toBe('app/Jobs/ConcreteJob.php');
    expect($result->violations()[0]->message)->toContain('No matching test found');
});

it('skips abstract entrypoints — base classes do not need mirror tests', function () {
    writeFile(
        $this->fixturePath . '/app/Console/Commands/BaseCommand.php',
        commandSource('App\Console\Commands', 'BaseCommand', abstract: true),
    );
    writeFile(
        $this->fixturePath . '/app/Jobs/BaseJob.php',
        jobSource('App\Jobs', 'BaseJob', abstract: true),
    );
    writeFile(
        $this->fixturePath . '/app/Http/Controllers/AbstractController.php',
        '<?php namespace App\Http\Controllers; abstract class AbstractController {}',
    );

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('detects FQ-extends without a use-import', function () {
    writeFile(
        $this->fixturePath . '/app/Foo/MyCmd.php',
        '<?php namespace App\Foo; final class MyCmd extends \Illuminate\Console\Command {}',
    );

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->violations())->toHaveCount(1);
    expect($result->violations()[0]->message)->toContain('Console command');
});

it('resolves aliased imports — `use ... as X` flows through the import table', function () {
    writeFile(
        $this->fixturePath . '/app/Foo/MyCmd.php',
        <<<'PHP'
        <?php
        namespace App\Foo;
        use Illuminate\Console\Command as BaseCmd;
        final class MyCmd extends BaseCmd {}
        PHP,
    );

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->violations())->toHaveCount(1);
    expect($result->violations()[0]->message)->toContain('Console command');
});

it('does not detect classes whose parent lives in vendor at non-canonical paths — documented shallow-walk limitation', function () {
    // `VendorBase` is not in the consumer's `app/` tree. At a non-canonical
    // path, the walk hits a dead end and we cannot tell whether `VendorBase`
    // extends Command — the misplacement scan misses it. The remediation is
    // to move the class into `app/Console/Commands/`, where the folder scan
    // catches it regardless of parentage (see the next test).
    writeFile(
        $this->fixturePath . '/app/Foo/MyCmd.php',
        <<<'PHP'
        <?php
        namespace App\Foo;
        use Acme\VendorBase;
        final class MyCmd extends VendorBase {}
        PHP,
    );

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('requires a mirror test for a vendor-base command at the canonical path (folder scan is parentage-agnostic)', function () {
    // Regression guard. A class that extends a vendor-supplied base class
    // (Spatie\…\ShortScheduleCommand, Lorisleiva\…\AsCommand, or a shared
    // package's own BaseJob) sits at `app/Console/Commands/...`. The
    // misplacement scan misses it because the parent walk dies at the
    // vendor boundary — but the folder scan requires a mirror test
    // regardless. Without this layer, vendor-base entrypoints silently
    // drop test coverage on adoption.
    writeFile(
        $this->fixturePath . '/app/Console/Commands/MyVendorCmd.php',
        <<<'PHP'
        <?php
        namespace App\Console\Commands;
        use Acme\VendorBase;
        final class MyVendorCmd extends VendorBase {}
        PHP,
    );

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->violations())->toHaveCount(1);
    expect($result->violations()[0]->file)->toBe('app/Console/Commands/MyVendorCmd.php');
    expect($result->violations()[0]->message)->toContain('No matching test found');
});

it('skips trait, enum, and interface-only files in canonical folders — documented relaxation', function () {
    // Old behavior required a mirror test for any `.php` file under the
    // canonical folder regardless of what it declared. New behavior keys off
    // the class map, which is built from `Class_` AST nodes only — traits,
    // enums, and interface-only files don't appear and are silently skipped.
    // A trait at `app/Jobs/Helpers/MyTrait.php` is a helper, not an
    // entrypoint. Pin this so a behavior change shows up.
    writeFile(
        $this->fixturePath . '/app/Jobs/Helpers/Notifies.php',
        '<?php namespace App\Jobs\Helpers; trait Notifies {}',
    );
    writeFile(
        $this->fixturePath . '/app/Jobs/Helpers/Status.php',
        '<?php namespace App\Jobs\Helpers; enum Status: string { case Pending = "pending"; }',
    );
    writeFile(
        $this->fixturePath . '/app/Jobs/Helpers/Notifiable.php',
        '<?php namespace App\Jobs\Helpers; interface Notifiable {}',
    );

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('requires a mirror test for any concrete class at the canonical path, even non-entrypoint helpers', function () {
    // The canonical folder IS the contract: anything you put there needs
    // a test. A helper / DTO sitting in `app/Console/Commands/` or
    // `app/Jobs/` is treated like a command/job — either author a test or
    // move it out. Mirrors the old folder-based behavior.
    writeFile(
        $this->fixturePath . '/app/Jobs/Helpers/Formatter.php',
        '<?php namespace App\Jobs\Helpers; final class Formatter {}',
    );

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->violations())->toHaveCount(1);
    expect($result->violations()[0]->file)->toBe('app/Jobs/Helpers/Formatter.php');
});

it('does not walk interface inheritance — documented limitation', function () {
    // Class implements a custom interface that itself extends ShouldQueue.
    // We do not chase interface-extends; the user must declare ShouldQueue
    // directly (or via a parent class on the chain).
    writeFile(
        $this->fixturePath . '/app/Contracts/MyJob.php',
        <<<'PHP'
        <?php
        namespace App\Contracts;
        use Illuminate\Contracts\Queue\ShouldQueue;
        interface MyJob extends ShouldQueue {}
        PHP,
    );
    writeFile(
        $this->fixturePath . '/app/Workflows/SendEmail.php',
        <<<'PHP'
        <?php
        namespace App\Workflows;
        use App\Contracts\MyJob;
        final class SendEmail implements MyJob {}
        PHP,
    );

    $result = (new TestParityValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('skips a category listed in ignoredScanPaths and bypasses misplacement detection too', function () {
    writeFile(
        $this->fixturePath . '/app/Workflows/MisplacedJob.php',
        jobSource('App\Workflows', 'MisplacedJob'),
    );

    $result = (new TestParityValidator(
        $this->fixturePath,
        ignoredScanPaths: ['app/Jobs'],
    ))->validate();

    expect($result->passed())->toBeTrue();
});

it('exposes its name on both the validator and the result', function () {
    $validator = new TestParityValidator($this->fixturePath);

    expect($validator->name())->toBe('TestParity');
    expect($validator->validate()->validator)->toBe('TestParity');
});

it('strict mode bypasses ignoredScanPaths and re-enables detection', function () {
    writeFile(
        $this->fixturePath . '/app/Workflows/MisplacedJob.php',
        jobSource('App\Workflows', 'MisplacedJob'),
    );

    $validator = new TestParityValidator(
        $this->fixturePath,
        ignoredScanPaths: ['app/Jobs'],
    );

    expect($validator->validate()->passed())->toBeTrue();

    $validator->setStrict(true);

    $strict = $validator->validate();
    expect($strict->passed())->toBeFalse();
    expect($strict->violations())->toHaveCount(1);
    expect($strict->violations()[0]->file)->toBe('app/Workflows/MisplacedJob.php');
});
