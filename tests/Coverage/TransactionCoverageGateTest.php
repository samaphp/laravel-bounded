<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Samaphp\LaravelBounded\Coverage\TransactionCoverageGate;

beforeEach(function () {
    $this->fixturePath = sys_get_temp_dir() . '/laravel-bounded-cov-' . uniqid();
    mkdir($this->fixturePath . '/app/Services', recursive: true);
});

afterEach(function () {
    if (isset($this->fixturePath) && is_dir($this->fixturePath)) {
        File::deleteDirectory($this->fixturePath);
    }
});

it('passes when no Transaction::run call sites exist', function () {
    file_put_contents(
        $this->fixturePath . '/app/Services/Plain.php',
        "<?php\nfinal class Plain { public function execute(): void {} }\n",
    );

    $result = (new TransactionCoverageGate())->check(
        $this->fixturePath . '/app',
        $this->fixturePath . '/coverage.xml',
    );

    expect($result->passed)->toBeTrue();
    expect($result->totalCallSites)->toBe(0);
});

it('returns an error when coverage report is missing and call sites exist', function () {
    file_put_contents(
        $this->fixturePath . '/app/Services/Foo.php',
        "<?php\nclass Foo { public function execute() { Transaction::run(fn () => 1); } }\n",
    );

    $result = (new TransactionCoverageGate())->check(
        $this->fixturePath . '/app',
        $this->fixturePath . '/missing-coverage.xml',
    );

    expect($result->passed)->toBeFalse();
    expect($result->error)->toContain('not found');
});

it('passes when every call site has non-zero coverage', function () {
    $servicePath = $this->fixturePath . '/app/Services/Foo.php';
    file_put_contents(
        $servicePath,
        "<?php\nclass Foo {\n    public function execute() {\n        Transaction::run(fn () => 1);\n    }\n}\n",
    );

    $resolved = realpath($servicePath);
    $clover = sprintf(
        '<?xml version="1.0"?>
<coverage>
    <project>
        <file name="%s">
            <line num="4" type="stmt" count="3"/>
        </file>
    </project>
</coverage>',
        $resolved,
    );
    file_put_contents($this->fixturePath . '/coverage.xml', $clover);

    $result = (new TransactionCoverageGate())->check(
        $this->fixturePath . '/app',
        $this->fixturePath . '/coverage.xml',
    );

    expect($result->passed)->toBeTrue();
    expect($result->totalCallSites)->toBe(1);
});

it('fails when a call site has zero coverage', function () {
    $servicePath = $this->fixturePath . '/app/Services/Foo.php';
    file_put_contents(
        $servicePath,
        "<?php\nclass Foo {\n    public function execute() {\n        Transaction::run(fn () => 1);\n    }\n}\n",
    );

    $resolved = realpath($servicePath);
    $clover = sprintf(
        '<?xml version="1.0"?>
<coverage>
    <project>
        <file name="%s">
            <line num="4" type="stmt" count="0"/>
        </file>
    </project>
</coverage>',
        $resolved,
    );
    file_put_contents($this->fixturePath . '/coverage.xml', $clover);

    $result = (new TransactionCoverageGate())->check(
        $this->fixturePath . '/app',
        $this->fixturePath . '/coverage.xml',
    );

    expect($result->passed)->toBeFalse();
    expect($result->totalCallSites)->toBe(1);
    expect($result->uncoveredCallSites)->toHaveCount(1);
    expect($result->uncoveredCallSites[0][1])->toBe(4);
});

it('detects instance call patterns ($this->transaction->run)', function () {
    $servicePath = $this->fixturePath . '/app/Services/Bar.php';
    file_put_contents(
        $servicePath,
        "<?php\nclass Bar {\n    public function execute() {\n        \$this->transaction->run(fn () => 1);\n    }\n}\n",
    );

    $resolved = realpath($servicePath);
    $clover = sprintf(
        '<?xml version="1.0"?>
<coverage>
    <project>
        <file name="%s">
            <line num="4" type="stmt" count="0"/>
        </file>
    </project>
</coverage>',
        $resolved,
    );
    file_put_contents($this->fixturePath . '/coverage.xml', $clover);

    $result = (new TransactionCoverageGate())->check(
        $this->fixturePath . '/app',
        $this->fixturePath . '/coverage.xml',
    );

    expect($result->passed)->toBeFalse();
    expect($result->uncoveredCallSites)->toHaveCount(1);
});
