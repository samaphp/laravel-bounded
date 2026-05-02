<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Tests\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Samaphp\LaravelBounded\PHPStan\Helpers\ZoneClassifier;
use Samaphp\LaravelBounded\PHPStan\Rules\FacadeZoneRule;

/**
 * @extends RuleTestCase<FacadeZoneRule>
 */
final class FacadeZoneRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new FacadeZoneRule(new ZoneClassifier());
    }

    public function testFlagsFacadeUseInService(): void
    {
        $this->analyse([__DIR__ . '/data/app/Services/Order/FacadeUsingService.php'], [
            [
                'Facade [Illuminate\Support\Facades\Auth] used in a logic zone. Facades are permitted only in app/Providers and app/Http/Middleware. Inject the underlying contract via constructor instead.',
                13,
            ],
        ]);
    }

    public function testAllowsContractInjectionInService(): void
    {
        $this->analyse([__DIR__ . '/data/app/Services/Order/ContractInjectingService.php'], []);
    }
}
