<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * `Bus::chain(...)` and `Bus::batch(...)` arguments must be array literals
 * at the call site — composition has to be readable in the file, not
 * built dynamically elsewhere. Non-literal arguments hide the steps from
 * the reader.
 *
 * @implements Rule<StaticCall>
 */
final class BusChainBatchLiteralRule implements Rule
{
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
        if ($className !== 'Bus' && ! str_ends_with($className, '\\Bus')) {
            return [];
        }

        $methodName = $node->name instanceof Node\Identifier ? $node->name->name : null;
        if (! in_array($methodName, ['chain', 'batch'], true)) {
            return [];
        }

        if (count($node->args) === 0) {
            return [];
        }

        $firstArg = $node->args[0]->value ?? null;
        if ($firstArg instanceof Array_) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Bus::%s() must be called with a literal array of jobs at the call site. Composition has to be readable in the file; building the chain/batch dynamically (e.g., from a variable) hides the steps from the reader.',
                $methodName,
            ))->identifier('bounded.busChainBatchLiteral')->build(),
        ];
    }
}
