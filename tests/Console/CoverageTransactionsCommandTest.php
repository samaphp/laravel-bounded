<?php

declare(strict_types=1);

it('arch:coverage:transactions is registered', function () {
    expect(\Illuminate\Support\Facades\Artisan::all())
        ->toHaveKey('arch:coverage:transactions');
});

it('returns success when no app/ directory exists', function () {
    $this->artisan('arch:coverage:transactions', ['--report' => 'nonexistent.xml'])
        ->assertExitCode(0);
});
