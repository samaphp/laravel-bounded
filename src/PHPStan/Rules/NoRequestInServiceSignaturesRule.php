<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Samaphp\LaravelBounded\PHPStan\Helpers\ZoneClassifier;

/**
 * Rejects `Illuminate\Http\Request` as a parameter type in any method
 * signature inside `app/Services`. Services receive validated DTOs,
 * not raw Request objects; the Request lives only in controllers.
 *
 * **Known edge case.** The rule matches the bare `Request` symbol to
 * catch the common `use Illuminate\Http\Request;` import pattern. If a
 * service file does `use App\DTO\Request;` and accepts `Request $param`,
 * the rule will false-positive on the user's custom DTO. This is a
 * heuristic limitation — proper fix requires PHPStan's name-resolution
 * scope to get the resolved FQN. For now, avoid naming custom types
 * `Request` in services, or rename them to something more specific
 * (`OrderRequest`, `PaymentRequest`). Suffix-named DTOs like
 * `CreateOrderRequest` are already handled correctly (see test).
 *
 * @implements Rule<ClassMethod>
 */
final class NoRequestInServiceSignaturesRule implements Rule
{
    public function __construct(
        private readonly ZoneClassifier $zones,
    ) {
    }

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param  ClassMethod  $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        if (! $this->zones->isUnderPath($file, 'app/Services')) {
            return [];
        }

        $errors = [];
        foreach ($node->params as $param) {
            if ($param->type === null) {
                continue;
            }

            $typeStr = $this->normalizeType($param->type);
            // Match the framework's Request only — exact FQN, or the bare
            // `Request` symbol (resolves via `use Illuminate\Http\Request`).
            // Do NOT suffix-match `\\Request` because legitimate DTOs named
            // `CreateOrderRequest`, `RegisterUserRequest`, etc. would
            // false-positive — those *are* the recommended pattern.
            if ($typeStr === 'Illuminate\\Http\\Request' || $typeStr === 'Request') {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Method [%s::%s] accepts an HTTP Request parameter — services must receive validated DTOs only. Move the Request handling into the controller and pass a typed DTO.',
                    $this->classNameForScope($scope),
                    $node->name->name,
                ))->identifier('bounded.noRequestInServiceSignatures')->build();
            }
        }

        return $errors;
    }

    private function normalizeType(Node $type): string
    {
        if ($type instanceof Node\Identifier) {
            return $type->name;
        }
        if ($type instanceof Node\Name) {
            return $type->toString();
        }
        if ($type instanceof Node\NullableType) {
            return $this->normalizeType($type->type);
        }

        return '';
    }

    private function classNameForScope(Scope $scope): string
    {
        $reflection = $scope->getClassReflection();

        return $reflection !== null ? $reflection->getName() : '<unknown>';
    }
}
