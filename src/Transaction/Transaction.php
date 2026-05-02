<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Transaction;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;

final class Transaction
{
    public function __construct(
        private readonly ConnectionResolverInterface $connections,
        private readonly Application $app,
    ) {
    }

    /**
     * Run the callback inside a single database transaction.
     *
     * Throws TransactionAlreadyOpenException if a transaction is already
     * open on the resolved connection — Transaction::run is the one and
     * only commit boundary per use case; nested calls are not permitted.
     *
     * Exception: when the app is running unit tests, an existing outer
     * transaction is treated as the test-fixture wrapper (e.g.
     * RefreshDatabase) and the callback is executed inside a savepoint.
     * The "no nesting" guard is for catching production programming
     * errors — not for fighting Laravel's test traits.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback, ?string $connection = null): mixed
    {
        $cx = $this->connections->connection($connection);

        $level = $cx->transactionLevel();
        if ($level > 0 && ! $this->app->runningUnitTests()) {
            $name = $cx instanceof Connection ? $cx->getName() : ($connection ?? 'default');
            throw TransactionAlreadyOpenException::forConnection(
                (string) $name,
                $level,
            );
        }

        return $cx->transaction($callback, attempts: 1);
    }
}
