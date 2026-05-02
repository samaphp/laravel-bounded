# laravel-bounded

An opinionated guardrail layer for Laravel: architectural conventions enforced as mechanical CI gates. The build either passes or the architecture is wrong. Runtime validators, PHPStan rules, and Deptrac config fail the build when conventions drift. Built for codebases where humans and AI agents both write code, and PR review is the bottleneck.

## What this solves

Three failure modes:

- **Drift accumulation.** Laravel allows closures in routes, inline `Validator::make`, helpers files, observers, model lifecycle hooks. Each is fine in isolation; each is a place for the codebase to drift. Bounded forbids them and fails the build the moment one appears.
- **Reviewer load.** Humans cannot keep up with structural drift in PR review. Mechanical gates run on every commit.
- **Agent normalization.** AI agents copy whatever pattern they see. One rogue feature folder normalizes a bad pattern across the codebase. Bounded rejects the wrong shape before it lands.

## What this rejects

Reject this package if any of these are dealbreakers:

- **Layer-folder, not feature-folder.** Code lives under `app/Services/{Domain}/`, `app/Repositories/{Domain}/`, `app/Http/Controllers/{Domain}/`. The layer is the top dimension. Feature-folder layouts (each feature owning its own controller-service-repository tree) are not supported.
- **No exceptions.** If a rule applies, it always applies. No carve-out for small projects, trivial cases, or "fix it later." Wrong rules get changed for everyone.
- **No magic.** No observers, no model lifecycle hooks (`boot`/`booted`), no `EventSubscriberInterface`, no `app/helpers.php`, no closures in routes. Every action has a visible call site.

## What it enforces

**Boot-time validators** (run on `arch:validate`):

- **`ZonePartition`** — `app/` paths assigned to `logic`, `framework_bridge`, or `repository` zones. Boot fails if any path is in multiple zones.
- **`SingleActionController`** (`app/Http/Controllers`) — each concrete controller must be `final`, have `__invoke`, no other public methods, no `Controller` suffix. Skips abstract classes.
- **`TestParity`** (`app/Http/Controllers`, `app/Console/Commands`, `app/Jobs`) — each file has a mirror test under `tests/Feature/` or `tests/Unit/`. Skips abstract classes. Fails on missing/empty scan paths.
- **`NoListeners`** (`app/Listeners`, `app/Observers`, all of `app/`) — no files in those directories; no class anywhere implements `EventSubscriberInterface`. Silent on missing/empty (no listeners is success).
- **`NoModelHooks`** (`app/Models`) — no model overrides `boot()` or `booted()`. Silent on missing/empty.
- **`AutoloadFiles`** (`composer.json`) — fails if `autoload.files` or `autoload-dev.files` is non-empty. Helpers files escape every layer; shared logic goes in a class under a layer.
- **`RouteHandler`** (`routes/*.php`) — each `Route::*(verb)` call must use a class-string handler (`Foo::class` or namespaced FQCN string). Closures, arrow functions, and `[Controller::class, 'method']` tuples fail. `Route::redirect` / `Route::view` / `Route::permanentRedirect` (no-handler forms) are allowed.

**PHPStan rules** (registered via `extension.neon`):

- `bounded.facadeZone` — facades allowed only in `framework_bridge` zones (Providers, Middleware), not in logic. One whitelisted exception: `Log::*`.
- `bounded.busChainBatchLiteral` — `Bus::chain` / `Bus::batch` first arg must be a literal array at the call site.
- `bounded.loggerEventKey` — every `Log::*` call must include `'event' => string` in context.
- `bounded.middlewareServiceImport` — middleware cannot import from `app/Services|Repositories|Queries|Integrations`.
- `bounded.noHttpTypesInServices` — services/repositories cannot return `Response` / `JsonResponse` / `View`.
- `bounded.noRequestInServiceSignatures` — services cannot accept `Illuminate\Http\Request` in signatures.
- `bounded.noInlineValidator` — `Validator::make(...)` and `validator($data, $rules)` forbidden in logic / repository zones. Validation belongs in Form Requests.

**Deptrac layer rules** (shipped `deptrac.yaml`):

- Layer chain: Controllers → Services → Repositories/Queries → Models → Eloquent.
- **Jobs may depend on Services only — not Models.** Jobs are thin transport; model reads or mutations go through a service.
- Eloquent imports allowed only in Repositories / Queries / Models.

**Coverage gate** (`arch:coverage:transactions`):

- Every `Transaction::run` call site must have non-zero test coverage. Asserts via Clover report.

**One commit boundary per use case** — `Transaction::run(callable)` opens at most one DB transaction; nested calls throw. Compatible with Laravel's `RefreshDatabase` test trait — see [Transaction service](#transaction-service).

## Webhook handlers

Webhooks use the same path as other endpoints: `routes/api.php` → invokable controller → Form Request (signature verified in `authorize()`) → Service. Webhook helper packages that introduce parallel controllers / jobs / models are rejected — they bypass the FormRequest layer.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- For coverage gate: `pcov` or `xdebug`

## Install

```bash
composer require samaphp/laravel-bounded
php artisan vendor:publish --tag=bounded-config
```

`config/bounded.php` is published — edit zones / ignore lists there. `phpstan/phpstan`, `larastan/larastan`, and `deptrac/deptrac` install transitively as runtime dependencies of the chain.

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

**Zones partition.** A path appears in exactly one zone. Boot fails (`InvalidConfigurationException`) on overlap.

## Generators

```bash
php artisan make:action  Order/Show              # → app/Http/Controllers/Order/Show.php
php artisan make:service Order/CreateOrder       # → app/Services/Order/CreateOrder.php
php artisan make:repository Order/OrderRepository # → app/Repositories/Order/OrderRepository.php
php artisan make:integration Stripe/Gateway      # → app/Integrations/Stripe/Gateway.php
php artisan make:bounded-job Email/Send          # → app/Jobs/Email/Send.php  (named with `bounded-` prefix to avoid colliding with Laravel core's make:job)
```

**Bare names without a `Domain/` or `Vendor/` segment are rejected.** `make:service CreateOrder` errors out and prints the corrected form. Per-feature subfolders are mandatory for layers that hold use-case-shaped code; the generators reject paths the validators would reject anyway.

## Validators (artisan)

```bash
php artisan arch:validate              # run all validators, respect ignore.paths
php artisan arch:validate --strict     # bypass ignore.paths for code violations; structural problems (ScanPathMissing/ScanPathEmpty) still respect ignore.paths
```

`ignore.paths` declares "this category isn't applicable to my project." That declaration holds in strict mode. Strict only bypasses ignore for **file-level violations** — so a path can't be used to permanently silence broken code.

## Full check chain

```bash
php artisan arch:check                 # arch:validate --strict → phpstan → deptrac → pest --coverage → coverage:transactions
php artisan arch:check --skip-coverage # skip the transaction-coverage gate
```

In `composer.json`:

```json
{
    "scripts": {
        "arch:check": "@php artisan arch:check"
    }
}
```

## PHPStan extension

In `phpstan.neon`:

```neon
includes:
    - vendor/samaphp/laravel-bounded/extension.neon
```

Rules registered via `phpstan.rules.rule` are listed under [What it enforces](#what-it-enforces).

## Deptrac config

```bash
cp vendor/samaphp/laravel-bounded/deptrac.yaml deptrac.yaml
vendor/bin/deptrac analyse
```

Layers and allowed dependencies are listed under [What it enforces](#what-it-enforces). Middleware boundaries are enforced by the `bounded.middlewareServiceImport` PHPStan rule, not Deptrac.

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

`Transaction::run(callable)` opens a single DB transaction, runs the callback, commits on success, rolls back on exception. **Nested calls throw `TransactionAlreadyOpenException`** — one commit boundary per use case.

**Compatible with `RefreshDatabase`.** When `App::runningUnitTests()` is true and a transaction is already open, the existing one is treated as the test-fixture wrapper and the callback runs in a savepoint. The "no nesting" guard catches production programming errors and does not interfere with Laravel's test trait.

## Coverage gate

```bash
vendor/bin/pest --coverage-clover=coverage.xml
php artisan arch:coverage:transactions
```

The gate scans `app/` for `Transaction::run` call sites, parses the Clover report, and asserts non-zero coverage on every call site. Fails with the list of uncovered lines if any. Requires `pcov` (recommended) or `xdebug` to generate the coverage report.

**Detection convention.** The gate matches two source patterns: `Transaction::run(` (static) and `->transaction->run(` (instance via property). The instance pattern requires the property to be named exactly `$transaction` — if you inject the service as `Transaction $tx` and call `$this->tx->run(...)`, the gate will miss it. Use `$transaction` as the property name.

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
