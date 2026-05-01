<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Exceptions;

use RuntimeException;
use Samaphp\LaravelBounded\Validators\Problem;

final class InvalidConfigurationException extends RuntimeException
{
    /**
     * @param  list<Problem>  $problems
     */
    public static function forZoneProblems(array $problems): self
    {
        $messages = array_map(
            static fn (Problem $p) => '  - ' . $p->message,
            $problems,
        );

        return new self(
            "Invalid bounded zone configuration:\n" . implode("\n", $messages),
        );
    }
}
