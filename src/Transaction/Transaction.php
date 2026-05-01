<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Transaction;

use Illuminate\Database\ConnectionResolverInterface;

final class Transaction
{
    public function __construct(
        private readonly ConnectionResolverInterface $connections,
    ) {
    }

    /**
     * Run the callback inside a single database transaction.
     *
     * Throws TransactionAlreadyOpenException if a transaction is already
     * open on the resolved connection — Transaction::run is the one and
     * only commit boundary per use case; nested calls are not permitted.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback, ?string $connection = null): mixed
    {
        $cx = $this->connections->connection($connection);

        $level = $cx->transactionLevel();
        if ($level > 0) {
            throw TransactionAlreadyOpenException::forConnection(
                (string) $cx->getName(),
                $level,
            );
        }

        return $cx->transaction($callback, attempts: 1);
    }
}
