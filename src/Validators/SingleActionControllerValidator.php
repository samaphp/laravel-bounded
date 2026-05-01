<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Validators;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Validates single-action controller invariants under app/Http/Controllers.
 *
 * Each concrete controller must be:
 *   - `final`
 *   - declared with an `__invoke` method (the single action)
 *   - free of additional public methods (other than `__construct`)
 *   - named without a `Controller` suffix (use action verbs: Show, List, ...)
 *
 * Skips abstract classes — treated as framework integration bridges, not
 * concrete controllers. If a concrete controller is mistakenly marked
 * `abstract`, this validator stays silent; remove the abstract keyword.
 */
final class SingleActionControllerValidator extends PathScanningValidator
{
    public function name(): string
    {
        return 'SingleActionController';
    }

    /**
     * @return list<string>
     */
    protected function scanPaths(): array
    {
        return ['app/Http/Controllers'];
    }

    /**
     * @return list<Violation>
     */
    protected function checkFile(string $absolutePath): array
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $contents = (string) file_get_contents($absolutePath);
        $ast = $parser->parse($contents) ?? [];

        $finder = new NodeFinder();
        /** @var Node\Stmt\Class_|null $class */
        $class = $finder->findFirstInstanceOf($ast, Node\Stmt\Class_::class);

        if ($class === null || $class->isAbstract() || $class->name === null) {
            return [];
        }

        $relativeFile = $this->relativeFromBase($absolutePath);
        $className = $class->name->name;
        $startLine = $class->getStartLine();
        $violations = [];

        if (! $class->isFinal()) {
            $violations[] = new Violation(
                file: $relativeFile,
                line: $startLine,
                message: sprintf(
                    'Class [%s] must be `final`. Single-action controllers are not designed for inheritance.',
                    $className,
                ),
            );
        }

        if (str_ends_with($className, 'Controller')) {
            $violations[] = new Violation(
                file: $relativeFile,
                line: $startLine,
                message: sprintf(
                    'Class [%s] must not have a `Controller` suffix. Use action-oriented naming (Show, List, Create, etc.).',
                    $className,
                ),
            );
        }

        $publicMethods = array_values(array_filter(
            $class->getMethods(),
            static fn (Node\Stmt\ClassMethod $method): bool => $method->isPublic() && $method->name->name !== '__construct',
        ));

        $publicMethodNames = array_map(
            static fn (Node\Stmt\ClassMethod $method): string => $method->name->name,
            $publicMethods,
        );

        if (! in_array('__invoke', $publicMethodNames, true)) {
            $violations[] = new Violation(
                file: $relativeFile,
                line: $startLine,
                message: sprintf(
                    'Class [%s] must define an `__invoke` method. Single-action controllers are invokable.',
                    $className,
                ),
            );
        }

        $extras = array_values(array_diff($publicMethodNames, ['__invoke']));
        if ($extras !== []) {
            $violations[] = new Violation(
                file: $relativeFile,
                line: $startLine,
                message: sprintf(
                    'Class [%s] has additional public methods beyond `__invoke`: [%s]. Single-action controllers expose `__invoke` only.',
                    $className,
                    implode(', ', $extras),
                ),
            );
        }

        return $violations;
    }
}
