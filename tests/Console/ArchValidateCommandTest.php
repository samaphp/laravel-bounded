<?php

declare(strict_types=1);

it('arch:validate is registered', function () {
    expect(\Illuminate\Support\Facades\Artisan::all())
        ->toHaveKey('arch:validate');
});

it('arch:validate runs and produces a summary line', function () {
    // Configure ignores so path scanners don't trip on the testbench app's missing dirs.
    config()->set('bounded.ignore.paths', [
        'app/Http/Controllers',
        'app/Console/Commands',
        'app/Jobs',
    ]);

    $this->artisan('arch:validate')
        ->expectsOutputToContain('Validators:')
        ->assertExitCode(0);
});

it('arch:validate exits 1 when path scanners hit missing dirs without ignore', function () {
    // Default config: no ignores. testbench app has no app/Http/Controllers, etc.
    // so TestParity + SingleActionController emit ScanPathMissing problems.
    $this->artisan('arch:validate')
        ->assertExitCode(1);
});

it('--strict overrides the configured ignore list', function () {
    config()->set('bounded.ignore.paths', [
        'app/Http/Controllers',
        'app/Console/Commands',
        'app/Jobs',
    ]);

    // Without --strict: ignores apply, no missing-path problems → exit 0.
    $this->artisan('arch:validate')->assertExitCode(0);

    // With --strict: ignores bypassed, scan paths missing → exit 1.
    $this->artisan('arch:validate', ['--strict' => true])->assertExitCode(1);
});
