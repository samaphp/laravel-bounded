<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Tests\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Samaphp\LaravelBounded\PHPStan\Rules\LoggerEventKeyRule;

/**
 * @extends RuleTestCase<LoggerEventKeyRule>
 */
final class LoggerEventKeyRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new LoggerEventKeyRule();
    }

    public function testAllowsLogCallWithEventKey(): void
    {
        $this->analyse([__DIR__ . '/data/app/Services/Order/LogWithEventService.php'], []);
    }

    public function testFlagsLogCallMissingEventKey(): void
    {
        $this->analyse([__DIR__ . '/data/app/Services/Order/LogWithoutEventService.php'], [
            [
                'Log::info() context array missing required `event` key. Add an event identifier (e.g., [\'event\' => \'order.created\', ...]).',
                13,
            ],
        ]);
    }
}
