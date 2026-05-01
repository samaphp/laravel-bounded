<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Validators;

final class Problem
{
    public function __construct(
        public readonly ProblemKind $kind,
        public readonly string $context,
        public readonly string $message,
    ) {
    }
}
