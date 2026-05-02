<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Samaphp\LaravelBounded\PHPStan\Helpers\ZoneClassifier;

/**
 * Forbids inline validation in logic and repository zones.
 *
 * `Validator::make(...)` and the `validator(...)` helper are escape hatches
 * around the FormRequest → DTO → Service pattern. When validation lives in
 * a controller/service/job, the rules and the action drift apart, the
 * structured error response Laravel builds for FormRequest is lost, and
 * tests can't validate the contract independently.
 *
 * Validation belongs in `app/Http/Requests/{Domain}/{Name}Request.php` —
 * a class that extends `Illuminate\Foundation\Http\FormRequest`. Controllers
 * type-hint the FormRequest, build a DTO from `validated()`, and pass it
 * to the service.
 *
 * @implements Rule<Node>
 */
final class NoInlineValidatorRule implements Rule
{
    public function __construct(
        private readonly ZoneClassifier $zones,
    ) {
    }

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        // Apply to logic and repository zones. framework_bridge (providers/middleware)
        // can technically use validators (rare, but legitimate for global
        // request validation), so we don't apply there.
        if (! $this->zones->isInAnyZone($file, ['logic', 'repository'])) {
            return [];
        }

        if ($node instanceof StaticCall) {
            return $this->checkStaticCall($node);
        }

        if ($node instanceof FuncCall) {
            return $this->checkHelperCall($node);
        }

        return [];
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function checkStaticCall(StaticCall $node): array
    {
        if (! $node->class instanceof Node\Name) {
            return [];
        }

        $className = $node->class->toString();
        if ($className !== 'Illuminate\\Support\\Facades\\Validator'
            && $className !== 'Validator'
        ) {
            return [];
        }

        if (! $node->name instanceof Node\Identifier || $node->name->name !== 'make') {
            return [];
        }

        return [$this->error()];
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function checkHelperCall(FuncCall $node): array
    {
        if (! $node->name instanceof Node\Name) {
            return [];
        }

        $functionName = $node->name->toString();
        if ($functionName !== 'validator' && $functionName !== '\\validator') {
            return [];
        }

        // The validator() helper with zero args returns the factory — that's a
        // dependency-injection pattern, not inline validation. Only flag the
        // call form `validator($data, $rules)` that returns a Validator
        // instance directly.
        if ($node->getArgs() === []) {
            return [];
        }

        return [$this->error()];
    }

    private function error(): \PHPStan\Rules\IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            'Inline validation is not allowed in logic/repository zones. Move validation into a Form Request under app/Http/Requests/{Domain}/{Name}Request.php and have the controller type-hint that class. The controller builds a DTO from validated() and hands it to the service.',
        )->identifier('bounded.noInlineValidator')->build();
    }
}
