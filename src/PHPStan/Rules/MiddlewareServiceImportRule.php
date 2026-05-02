<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Use_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Samaphp\LaravelBounded\PHPStan\Helpers\ZoneClassifier;

/**
 * Forbids `app/Http/Middleware/**` from importing classes from logic
 * zones (`app/Services`, `app/Repositories`, `app/Queries`,
 * `app/Integrations`). Middleware that needs domain logic is mistyped —
 * make it a controller dispatching to a service.
 *
 * @implements Rule<Use_>
 */
final class MiddlewareServiceImportRule implements Rule
{
    private const FORBIDDEN_PREFIXES = [
        'App\\Services\\',
        'App\\Repositories\\',
        'App\\Queries\\',
        'App\\Integrations\\',
    ];

    public function __construct(
        private readonly ZoneClassifier $zones,
    ) {
    }

    public function getNodeType(): string
    {
        return Use_::class;
    }

    /**
     * @param  Use_  $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        if (! $this->zones->isUnderPath($file, 'app/Http/Middleware')) {
            return [];
        }

        $errors = [];
        foreach ($node->uses as $use) {
            $importedFqn = $use->name->toString();
            foreach (self::FORBIDDEN_PREFIXES as $prefix) {
                if (str_starts_with($importedFqn, $prefix)) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Middleware imports [%s] from a logic zone. Middleware must not contain business logic — if domain logic is needed, dispatch to a service from a controller instead.',
                        $importedFqn,
                    ))->identifier('bounded.middlewareServiceImport')->build();
                    break;
                }
            }
        }

        return $errors;
    }
}
