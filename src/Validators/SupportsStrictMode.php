<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Validators;

/**
 * Marker for validators that honour `arch:validate --strict`.
 *
 * Strict mode bypasses `ignore.paths` for file-level violations. The
 * exact semantics differ per validator — see each implementation.
 *
 * `ArchValidateCommand` uses this interface to decide which validators
 * to flip into strict mode; non-implementers are unaffected.
 */
interface SupportsStrictMode
{
    public function setStrict(bool $strict): void;
}
