<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Tests\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Samaphp\LaravelBounded\PHPStan\Helpers\ZoneClassifier;
use Samaphp\LaravelBounded\PHPStan\Rules\NoInlineValidatorRule;

/**
 * @extends RuleTestCase<NoInlineValidatorRule>
 */
final class NoInlineValidatorRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoInlineValidatorRule(new ZoneClassifier());
    }

    public function testFlagsValidatorMakeInService(): void
    {
        $this->analyse([__DIR__ . '/data/app/Services/Order/InlineValidatorMakeService.php'], [
            [
                'Inline validation is not allowed in logic/repository zones. Move validation into a Form Request under app/Http/Requests/{Domain}/{Name}Request.php and have the controller type-hint that class. The controller builds a DTO from validated() and hands it to the service.',
                16,
            ],
        ]);
    }

    public function testFlagsValidatorHelperInService(): void
    {
        $this->analyse([__DIR__ . '/data/app/Services/Order/InlineValidatorHelperService.php'], [
            [
                'Inline validation is not allowed in logic/repository zones. Move validation into a Form Request under app/Http/Requests/{Domain}/{Name}Request.php and have the controller type-hint that class. The controller builds a DTO from validated() and hands it to the service.',
                14,
            ],
        ]);
    }

    public function testAllowsValidatorMakeInsideFormRequest(): void
    {
        // app/Http/Requests is not in any logic zone, so the rule does not
        // apply. FormRequest classes are the legitimate home of validation.
        $this->analyse([__DIR__ . '/data/app/Http/Requests/Order/CreateOrderRequest.php'], []);
    }
}
