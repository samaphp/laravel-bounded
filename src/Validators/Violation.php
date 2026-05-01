<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Validators;

final class Violation
{
    public function __construct(
        public readonly string $file,
        public readonly ?int $line,
        public readonly string $message,
    ) {
    }
}
