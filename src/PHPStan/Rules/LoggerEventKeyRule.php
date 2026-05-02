<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Every Log facade call must include an `event` key in the context array.
 * Events are dot-notation lowercase identifiers (e.g., `order.created`,
 * `payment.failed`) so observability tools can pivot logs by event
 * without parsing free-form messages.
 *
 * Currently scoped to the `Log::*` facade. Instance-method calls on
 * `LoggerInterface` are out of MVP scope (require type-aware analysis);
 * the validator catches structural cases statically and the convention
 * carries via code review for the rest.
 *
 * @implements Rule<StaticCall>
 */
final class LoggerEventKeyRule implements Rule
{
    private const LOG_METHODS = [
        'debug', 'info', 'notice', 'warning', 'error',
        'critical', 'alert', 'emergency', 'log',
    ];

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @param  StaticCall  $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->class instanceof Node\Name) {
            return [];
        }

        $className = $node->class->toString();
        if ($className !== 'Log' && ! str_ends_with($className, '\\Log')) {
            return [];
        }

        $methodName = $node->name instanceof Node\Identifier ? $node->name->name : null;
        if (! in_array($methodName, self::LOG_METHODS, true)) {
            return [];
        }

        // log() takes ($level, $message, $context); other methods take ($message, $context).
        $contextArgIndex = $methodName === 'log' ? 2 : 1;

        if (count($node->args) <= $contextArgIndex) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Log::%s() called without context array. Every log call must include an `event` key (e.g., Log::%s($msg, [\'event\' => \'order.created\'])).',
                    $methodName,
                    $methodName,
                ))->identifier('bounded.loggerEventKey')->build(),
            ];
        }

        $contextArg = $node->args[$contextArgIndex]->value ?? null;

        if (! $contextArg instanceof Array_) {
            // Non-literal context — can't statically verify. Skip silently.
            return [];
        }

        foreach ($contextArg->items as $item) {
            if ($item === null) {
                continue;
            }
            if ($item->key instanceof String_ && $item->key->value === 'event') {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Log::%s() context array missing required `event` key. Add an event identifier (e.g., [\'event\' => \'order.created\', ...]).',
                $methodName,
            ))->identifier('bounded.loggerEventKey')->build(),
        ];
    }
}
