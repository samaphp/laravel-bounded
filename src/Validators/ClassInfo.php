<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Validators;

/**
 * Per-file snapshot of a single PHP class as parsed from `app/`.
 *
 * Used by `TestParityValidator` to walk inheritance chains for command/job
 * detection without re-parsing files.
 */
final class ClassInfo
{
    /**
     * @param  list<string>  $interfaces  Resolved fully-qualified interface names.
     */
    public function __construct(
        public readonly string $absolutePath,
        public readonly string $fqn,
        public readonly ?string $parentFqn,
        public readonly array $interfaces,
        public readonly bool $isAbstract,
        public readonly int $classStartLine,
    ) {
    }
}
