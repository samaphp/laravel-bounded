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
 * Rejects HTTP response/view types as return signatures inside
 * `app/Services` and `app/Repositories`. Services return strings, DTOs,
 * or domain objects; controllers wrap them into HTTP responses.
 *
 * @implements Rule<ClassMethod>
 */
final class NoHttpTypesInServicesRule implements Rule
{
    private const FORBIDDEN_TYPES = [
        'Illuminate\\Http\\Response',
        'Illuminate\\Http\\JsonResponse',
        'Illuminate\\Http\\RedirectResponse',
        'Illuminate\\Contracts\\View\\View',
        'Symfony\\Component\\HttpFoundation\\Response',
    ];

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

        if (! $this->zones->isUnderPath($file, 'app/Services')
            && ! $this->zones->isUnderPath($file, 'app/Repositories')) {
            return [];
        }

        if ($node->returnType === null) {
            return [];
        }

        $returnTypeStr = $this->normalizeType($node->returnType);

        foreach (self::FORBIDDEN_TYPES as $forbidden) {
            if ($returnTypeStr === $forbidden || str_ends_with($returnTypeStr, '\\' . $this->shortName($forbidden))) {
                return [
                    RuleErrorBuilder::message(sprintf(
                        'Method [%s::%s] returns [%s] — HTTP types are forbidden in services/repositories. Return strings, DTOs, or domain objects; let the controller wrap them.',
                        $this->classNameForScope($scope),
                        $node->name->name,
                        $returnTypeStr,
                    ))->identifier('bounded.noHttpTypesInServices')->build(),
                ];
            }
        }

        return [];
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

    private function shortName(string $fqn): string
    {
        $parts = explode('\\', $fqn);

        return end($parts);
    }

    private function classNameForScope(Scope $scope): string
    {
        $reflection = $scope->getClassReflection();

        return $reflection !== null ? $reflection->getName() : '<unknown>';
    }
}
