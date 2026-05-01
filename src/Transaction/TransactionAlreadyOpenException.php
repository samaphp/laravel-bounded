<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Transaction;

use RuntimeException;

final class TransactionAlreadyOpenException extends RuntimeException
{
    public static function forConnection(string $connection, int $level): self
    {
        return new self(
            "Cannot open a new transaction on connection [{$connection}] — "
            . "a transaction is already in progress (level {$level}). "
            . 'Transaction::run() opens at most one commit boundary per use case; nested calls are not permitted.',
        );
    }
}
