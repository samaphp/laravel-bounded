<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Tests\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Samaphp\LaravelBounded\PHPStan\Helpers\ZoneClassifier;
use Samaphp\LaravelBounded\PHPStan\Rules\NoRequestInServiceSignaturesRule;

/**
 * @extends RuleTestCase<NoRequestInServiceSignaturesRule>
 */
final class NoRequestInServiceSignaturesRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoRequestInServiceSignaturesRule(new ZoneClassifier());
    }

    public function testFlagsServiceMethodWithRequestParam(): void
    {
        $this->analyse([__DIR__ . '/data/app/Services/Order/RequestParamService.php'], [
            [
                'Method [App\Services\Order\RequestParamService::execute] accepts an HTTP Request parameter — services must receive validated DTOs only. Move the Request handling into the controller and pass a typed DTO.',
                11,
            ],
        ]);
    }

    public function testAllowsServiceMethodWithDtoParam(): void
    {
        $this->analyse([__DIR__ . '/data/app/Services/Order/DtoParamService.php'], []);
    }
}
