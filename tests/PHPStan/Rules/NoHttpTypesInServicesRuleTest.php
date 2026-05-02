<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Tests\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Samaphp\LaravelBounded\PHPStan\Helpers\ZoneClassifier;
use Samaphp\LaravelBounded\PHPStan\Rules\NoHttpTypesInServicesRule;

/**
 * @extends RuleTestCase<NoHttpTypesInServicesRule>
 */
final class NoHttpTypesInServicesRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoHttpTypesInServicesRule(new ZoneClassifier());
    }

    public function testFlagsServiceReturningJsonResponse(): void
    {
        $this->analyse([__DIR__ . '/data/app/Services/Order/HttpJsonResponseService.php'], [
            [
                'Method [App\Services\Order\HttpJsonResponseService::execute] returns [Illuminate\Http\JsonResponse] — HTTP types are forbidden in services/repositories. Return strings, DTOs, or domain objects; let the controller wrap them.',
                11,
            ],
        ]);
    }

    public function testAllowsServiceReturningString(): void
    {
        $this->analyse([__DIR__ . '/data/app/Services/Order/StringReturnService.php'], []);
    }
}
