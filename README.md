# laravel-bounded

Boundary enforcement for Laravel — invokable controllers, transaction commit boundaries, log event keys, DTO discipline, facade zones, middleware foreclosure. Validators run at artisan boot; static rules ship for PHPStan and Deptrac.

## Status

Scaffolded 2026-05-01. Not implemented yet — planning happens in the next step.

## Quick start

```bash
docker compose up -d                              # primary dev container (PHP 8.4)
docker compose exec app composer install
docker compose exec app vendor/bin/pest
```

## Test matrix

Run Pest against any supported PHP version:

```bash
docker compose --profile test run --rm test-php83 vendor/bin/pest
docker compose --profile test run --rm test-php84 vendor/bin/pest
docker compose --profile test run --rm test-php85 vendor/bin/pest
```

## Support windows (verified 2026-05-01)

| Stack    | Versions targeted                                | Source                                           |
| -------- | ------------------------------------------------ | ------------------------------------------------ |
| PHP      | 8.3 (security-only), 8.4 (active), 8.5 (active)  | https://www.php.net/supported-versions.php       |
| Laravel  | 12 (active bug fixes), 13 (active bug fixes)     | https://laravel.com/docs/releases                |

PHP 8.2 excluded — Laravel 13 requires 8.3+, and 8.2 drops to EOL Dec 31, 2026.
Laravel 11 excluded — security-only support ended Mar 12, 2026.

## Variables (`.env`)

None required for the package itself. Consumer projects supply their own `.env`.

## Staging

Not applicable — this is a Composer library, not a deployable app. Releases go to Packagist.
