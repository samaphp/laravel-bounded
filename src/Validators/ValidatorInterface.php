<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Validators;

interface ValidatorInterface
{
    public function name(): string;

    public function validate(): ValidatorResult;
}
