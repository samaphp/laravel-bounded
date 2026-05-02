# laravel-bounded

Boundary enforcement for Laravel. The package's pitch is *"the build either passes or the architecture is wrong"* — runtime validators, PHPStan rules, and Deptrac config that fail loudly when conventions drift.

What it enforces:

- **Single-action invokable controllers** — `final`, one `__invoke` per file, no `Controller` suffix.
- **One commit boundary per use case** — `Transaction::run(callable)` opens at most one DB transaction; nested calls throw.
- **Test parity** — every controller / command / job has a mirror test at the expected path.
- **No implicit execution** — no listeners, no observers, no model lifecycle hooks (`boot`/`booted`), no `EventSubscriberInterface` implementations.
- **Zone partitioning** — `app/` paths assigned to `logic`, `framework_bridge`, or `repository` zones; partition enforced at boot.
- **Facade discipline** — facades allowed only in `framework_bridge` zones (Providers, Middleware), not in logic.
- **HTTP-type discipline** — `Response`/`View` types forbidden in service/repository return signatures; `Request` forbidden in service method signatures.
- **Logger event keys** — every `Log::*` call must include `'event' => string` in context.
- **`Bus::chain` / `Bus::batch` literal arrays** — composition must be readable at the call site.
- **Middleware service-import ban** — middleware cannot import from `app/Services|Repositories|Queries|Integrations`.
- **Transaction coverage gate** — every line containing `Transaction::run` must have non-zero coverage.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- For coverage gate: `pcov` or `xdebug`

## Install

```bash
composer require samaphp/laravel-bounded
php artisan vendor:publish --tag=bounded-config
```

`config/bounded.php` is published — edit zones / ignore lists there.

## Configuration

`config/bounded.php`:

```php
return [
    'zones' => [
        'logic' => ['app/Http/Controllers', 'app/Services', 'app/Integrations', 'app/Console/Commands', 'app/Jobs'],
        'framework_bridge' => ['app/Providers', 'app/Http/Middleware'],
        'repository' => ['app/Repositories', 'app/Queries'],
    ],
    'ignore' => [
        'paths' => [
            // 'app/Jobs', // suppress validators for projects that don't use queues
        ],
    ],
];
```

**Zones partition.** A path appears in exactly one zone, never two. The boot validator throws `InvalidConfigurationException` if any path is in multiple zones.

## Generators

```bash
php artisan make:action  Order/Show          # → app/Http/Controllers/Order/Show.php
php artisan make:service Order/CreateOrder   # → app/Services/Order/CreateOrder.php
php artisan make:repository OrderRepository  # → app/Repositories/OrderRepository.php
php artisan make:integration Stripe/Gateway  # → app/Integrations/Stripe/Gateway.php
php artisan make:bounded-job Email/Send      # → app/Jobs/Email/Send.php  (named with `bounded-` prefix to avoid colliding with Laravel core's make:job)
```

## Validators (artisan)

```bash
php artisan arch:validate              # run all validators, respect ignore.paths
php artisan arch:validate --strict     # bypass ignore.paths
```

Output groups violations and problems per validator, with per-line locations for code violations and per-context messages for config/structural problems.

## Full check chain

```bash
php artisan arch:check                 # arch:validate --strict → phpstan → deptrac → pest --coverage → coverage:transactions
php artisan arch:check --skip-coverage # skip the transaction-coverage gate
```

Add to consumer's `composer.json` for one-command invocation:

```json
{
    "scripts": {
        "arch:check": "@php artisan arch:check"
    }
}
```

Then: `composer arch:check`.

## PHPStan extension

Add to consumer's `phpstan.neon`:

```neon
includes:
    - vendor/samaphp/laravel-bounded/extension.neon
```

The extension ships six rules registered via `phpstan.rules.rule`:

- `bounded.facadeZone` — facades only in framework_bridge zones
- `bounded.busChainBatchLiteral` — `Bus::chain`/`batch` args must be array literals
- `bounded.loggerEventKey` — `Log::*` calls must have `event` key in context
- `bounded.middlewareServiceImport` — middleware can't import from logic zones
- `bounded.noHttpTypesInServices` — services/repositories can't return HTTP types
- `bounded.noRequestInServiceSignatures` — services can't accept `Request` in signatures

## Deptrac config

Copy `vendor/samaphp/laravel-bounded/deptrac.yaml` to project root, customize layers as needed:

```bash
cp vendor/samaphp/laravel-bounded/deptrac.yaml deptrac.yaml
vendor/bin/deptrac analyse
```

The shipped config defines 10 layers (Controllers, Commands, Services, Repositories, Queries, Integrations, Models, Jobs, Providers, plus a virtual `Eloquent` layer that restricts ORM imports to Repositories / Queries / Models) with allowed dependencies per the rule chain. Middleware boundaries are enforced by the `bounded.middlewareServiceImport` PHPStan rule, not Deptrac.

## Transaction service

```php
use Samaphp\LaravelBounded\Transaction\Transaction;

final class CreateOrder
{
    public function __construct(private readonly Transaction $transaction) {}

    public function execute(CreateOrderInput $input): Order
    {
        return $this->transaction->run(function () use ($input) {
            // persist + dispatch jobs explicitly
            return Order::create([...]);
        });
    }
}
```

`Transaction::run(callable)` opens a single DB transaction, runs the callback, commits on success, rolls back on exception. **Nested calls throw `TransactionAlreadyOpenException`** — one commit boundary per use case, by design.

## Coverage gate

```bash
vendor/bin/pest --coverage-clover=coverage.xml
php artisan arch:coverage:transactions
```

The gate scans `app/` for `Transaction::run` call sites, parses the Clover report, and asserts non-zero coverage on every call site. Fails with the list of uncovered lines if any. Requires `pcov` (recommended) or `xdebug` to generate the coverage report.

**Detection convention.** The gate matches two source patterns: `Transaction::run(` (static) and `->transaction->run(` (instance via property). The instance pattern requires the property to be named exactly `$transaction` — if you inject the service as `Transaction $tx` and call `$this->tx->run(...)`, the gate will miss it. Stick with `$transaction` as the property name.

## Validators reference

| Validator | Scope | Behavior |
|---|---|---|
| `ZonePartition` | `config/bounded.php`'s `zones` | Fails boot if any path is in multiple zones (config-level). |
| `TestParity` | `app/Http/Controllers`, `app/Console/Commands`, `app/Jobs` | Each file must have a mirror test under `tests/Feature/` or `tests/Unit/`. Fails loud on missing/empty scan paths. |
| `SingleActionController` | `app/Http/Controllers` | Each concrete controller must be `final`, have `__invoke`, no other public methods, no `Controller` suffix. Skips abstract classes. |
| `NoListeners` | `app/Listeners`, `app/Observers`, all of `app/` | No files in those directories; no class anywhere implements `EventSubscriberInterface`. Silent on missing/empty (no listeners is success). |
| `NoModelHooks` | `app/Models` | No model overrides `boot()` or `booted()`. Silent on missing/empty. |

## Contributing / package dev

```bash
docker compose up -d
docker compose exec app composer install
docker compose exec app vendor/bin/pest --no-coverage   # Pest 4 enforces coverage driver; --no-coverage works without pcov
```

### Test matrix (multi-PHP)

```bash
docker compose --profile test run --rm test-php83 vendor/bin/pest --no-coverage
docker compose --profile test run --rm test-php84 vendor/bin/pest --no-coverage
docker compose --profile test run --rm test-php85 vendor/bin/pest --no-coverage
```

### Support windows (verified 2026-05-02)

| Stack    | Versions targeted                                | Source                                           |
| -------- | ------------------------------------------------ | ------------------------------------------------ |
| PHP      | 8.3 (security-only), 8.4 (active), 8.5 (active)  | https://www.php.net/supported-versions.php       |
| Laravel  | 12 (active bug fixes), 13 (active bug fixes)     | https://laravel.com/docs/releases                |

PHP 8.2 excluded — Laravel 13 requires 8.3+, and 8.2 drops to EOL Dec 31, 2026.
Laravel 11 excluded — security-only support ended Mar 12, 2026.
