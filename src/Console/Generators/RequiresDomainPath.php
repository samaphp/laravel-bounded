<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Console\Generators;

/**
 * Forces Bounded generators to require a Domain/Name (or Vendor/Name) segment.
 *
 * The skill mandates per-feature subfolders for every layer that holds
 * use-case-shaped code. Without this guard, `make:service CreateOrder`
 * would silently emit `app/Services/CreateOrder.php` (flat) — a path
 * that violates the skill's own folder rule. Generators that produce
 * files outside the documented layout are a footgun: agents follow the
 * generator and end up with paths the validators will then reject.
 *
 * Applied to: make:action, make:service, make:repository,
 * make:integration, make:bounded-job.
 *
 * **Consumer requirement:** the using class must extend
 * `Illuminate\Console\GeneratorCommand`. The trait calls `parent::handle()`
 * to delegate the actual file generation; if Laravel ever renames or
 * changes the signature of that method, this contract breaks.
 */
trait RequiresDomainPath
{
    public function handle(): bool|null
    {
        $name = $this->getNameInput();

        if (! str_contains($name, '/') && ! str_contains($name, '\\')) {
            $segment = $this->name === 'make:integration' ? 'Vendor' : 'Domain';
            $this->components->error(sprintf(
                'Bounded layout requires a %s segment. Use `php artisan %s {%s}/{Name}`. Example: `php artisan %s %s/%s`.',
                $segment,
                $this->name,
                $segment,
                $this->name,
                $segment === 'Vendor' ? 'Stripe' : 'Order',
                $this->getExampleName(),
            ));

            return false;
        }

        return parent::handle();
    }

    protected function getExampleName(): string
    {
        return match ($this->name) {
            'make:action' => 'Show',
            'make:service' => 'CreateOrder',
            'make:repository' => 'OrderRepository',
            'make:integration' => 'StripeApi',
            'make:bounded-job' => 'SendOrderEmail',
            default => 'Example',
        };
    }
}
