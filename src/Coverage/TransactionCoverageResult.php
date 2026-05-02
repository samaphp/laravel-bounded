<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Coverage;

final class TransactionCoverageResult
{
    /**
     * @param  list<array{0: string, 1: int}>  $uncoveredCallSites  [absolute file path, line] pairs.
     */
    public function __construct(
        public readonly bool $passed,
        public readonly int $totalCallSites,
        public readonly array $uncoveredCallSites = [],
        public readonly ?string $error = null,
    ) {
    }

    public static function noCallSites(): self
    {
        return new self(passed: true, totalCallSites: 0);
    }

    public static function reportMissing(string $path): self
    {
        return new self(
            passed: false,
            totalCallSites: 0,
            error: sprintf(
                'Coverage report not found at [%s]. Run `vendor/bin/pest --coverage-clover=coverage.xml` first.',
                $path,
            ),
        );
    }
}
