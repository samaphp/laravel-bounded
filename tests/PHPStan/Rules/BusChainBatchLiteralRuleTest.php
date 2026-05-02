<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Tests\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Samaphp\LaravelBounded\PHPStan\Rules\BusChainBatchLiteralRule;

/**
 * @extends RuleTestCase<BusChainBatchLiteralRule>
 */
final class BusChainBatchLiteralRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BusChainBatchLiteralRule();
    }

    public function testAllowsLiteralArrayArgument(): void
    {
        $this->analyse([__DIR__ . '/data/app/Services/Order/BusChainLiteralService.php'], []);
    }

    public function testFlagsVariableArgument(): void
    {
        $this->analyse([__DIR__ . '/data/app/Services/Order/BusChainVariableService.php'], [
            [
                'Bus::chain() must be called with a literal array of jobs at the call site. Composition has to be readable in the file; building the chain/batch dynamically (e.g., from a variable) hides the steps from the reader.',
                14,
            ],
        ]);
    }
}
