<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Validators;

final class ValidatorResult
{
    /**
     * @param  list<Violation>  $violations
     * @param  list<Problem>  $problems
     */
    public function __construct(
        public readonly string $validator,
        private readonly array $violations = [],
        private readonly array $problems = [],
    ) {
    }

    public function passed(): bool
    {
        return $this->violations === [] && $this->problems === [];
    }

    /**
     * @return list<Violation>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    /**
     * @return list<Problem>
     */
    public function problems(): array
    {
        return $this->problems;
    }
}
