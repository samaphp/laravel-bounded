<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Samaphp\LaravelBounded\Transaction\Transaction;
use Samaphp\LaravelBounded\Transaction\TransactionAlreadyOpenException;

beforeEach(function () {
    Schema::create('items', function ($table) {
        $table->id();
        $table->string('name');
    });
});

it('commits the callback when no transaction is open', function () {
    $transaction = $this->app->make(Transaction::class);

    $transaction->run(function () {
        DB::table('items')->insert(['name' => 'persisted']);
    });

    expect(DB::table('items')->count())->toBe(1);
});

it('rolls back when the callback throws', function () {
    $transaction = $this->app->make(Transaction::class);

    expect(fn () => $transaction->run(function () {
        DB::table('items')->insert(['name' => 'should-roll-back']);
        throw new RuntimeException('oops');
    }))->toThrow(RuntimeException::class, 'oops');

    expect(DB::table('items')->count())->toBe(0);
});

it('throws TransactionAlreadyOpenException when nested in production mode', function () {
    $cx = Mockery::mock(ConnectionInterface::class);
    $cx->shouldReceive('transactionLevel')->once()->andReturn(1);
    $cx->shouldReceive('getName')->andReturn('test');

    $resolver = Mockery::mock(ConnectionResolverInterface::class);
    $resolver->shouldReceive('connection')->once()->with(null)->andReturn($cx);

    $app = Mockery::mock(Application::class);
    $app->shouldReceive('runningUnitTests')->once()->andReturn(false);

    expect(fn () => (new Transaction($resolver, $app))->run(fn () => null))
        ->toThrow(TransactionAlreadyOpenException::class);
});

it('does not throw when nested under the test-fixture transaction (RefreshDatabase)', function () {
    $transaction = $this->app->make(Transaction::class);

    DB::beginTransaction();

    try {
        $transaction->run(function () {
            DB::table('items')->insert(['name' => 'savepoint']);
        });

        expect(DB::table('items')->count())->toBe(1);
    } finally {
        DB::rollBack();
    }

    expect(DB::table('items')->count())->toBe(0);
});

it('passes attempts=1 to the underlying transaction call', function () {
    $cx = Mockery::mock(ConnectionInterface::class);
    $cx->shouldReceive('transactionLevel')->once()->andReturn(0);
    $cx->shouldReceive('getName')->andReturn('test');
    $cx->shouldReceive('transaction')
        ->once()
        ->withArgs(fn ($callback, $attempts) => is_callable($callback) && $attempts === 1)
        ->andReturn(null);

    $resolver = Mockery::mock(ConnectionResolverInterface::class);
    $resolver->shouldReceive('connection')->once()->with(null)->andReturn($cx);

    $app = Mockery::mock(Application::class);

    (new Transaction($resolver, $app))->run(fn () => null);
});
