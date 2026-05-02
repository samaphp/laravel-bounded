<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Tests\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Samaphp\LaravelBounded\PHPStan\Helpers\ZoneClassifier;
use Samaphp\LaravelBounded\PHPStan\Rules\MiddlewareServiceImportRule;

/**
 * @extends RuleTestCase<MiddlewareServiceImportRule>
 */
final class MiddlewareServiceImportRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new MiddlewareServiceImportRule(new ZoneClassifier());
    }

    public function testFlagsMiddlewareImportingService(): void
    {
        $this->analyse([__DIR__ . '/data/app/Http/Middleware/ServiceImportingMiddleware.php'], [
            [
                'Middleware imports [App\Services\Order\StringReturnService] from a logic zone. Middleware must not contain business logic — if domain logic is needed, dispatch to a service from a controller instead.',
                7,
            ],
        ]);
    }

    public function testAllowsMiddlewareImportingContracts(): void
    {
        $this->analyse([__DIR__ . '/data/app/Http/Middleware/CleanMiddleware.php'], []);
    }
}
