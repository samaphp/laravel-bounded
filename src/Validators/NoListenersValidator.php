<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Validators;

use PhpParser\Node;

/**
 * Anti-pattern detector: forbids event listeners, observers, and
 * EventSubscriberInterface implementations anywhere in `app/`.
 *
 * Listeners and observers are implicit-execution patterns — Laravel
 * fires them without a visible call site, so a reader of any service
 * file can't see what side effects run when ->save() / event() is
 * invoked. This package forbids them; dispatch events explicitly from
 * a service action instead.
 *
 * Three rules in one validator:
 *   1. `app/Listeners/` must not contain files
 *   2. `app/Observers/` must not contain files
 *   3. No class anywhere under `app/` implements `EventSubscriberInterface`
 *
 * Missing or empty `app/Listeners` / `app/Observers` is the *success*
 * case. This validator does not inherit `PathScanningValidator` because
 * its missing/empty semantics are inverted (the base barks; this rule
 * silently passes).
 */
final class NoListenersValidator implements ValidatorInterface
{
    private const FORBIDDEN_DIRS = ['app/Listeners', 'app/Observers'];

    /**
     * @param  list<string>  $ignoredScanPaths
     */
    public function __construct(
        private readonly string $basePath,
        private readonly array $ignoredScanPaths = [],
    ) {
    }

    public function name(): string
    {
        return 'NoListeners';
    }

    public function validate(): ValidatorResult
    {
        $violations = [];
        $prefix = rtrim($this->basePath, '/') . '/';

        // Rules 1 & 2: forbidden directories existing with files.
        foreach (self::FORBIDDEN_DIRS as $forbiddenDir) {
            if (in_array($forbiddenDir, $this->ignoredScanPaths, true)) {
                continue;
            }

            $absoluteDir = $prefix . $forbiddenDir;
            if (! is_dir($absoluteDir)) {
                continue;
            }

            foreach (PhpFileIterator::iterate($absoluteDir) as $file) {
                $relative = substr($file, strlen($prefix));
                $violations[] = new Violation(
                    file: $relative,
                    line: null,
                    message: sprintf(
                        'Forbidden file [%s] — listeners and observers are implicit-execution patterns. Dispatch events explicitly from a service action instead.',
                        $relative,
                    ),
                );
            }
        }

        // Rule 3: any class under app/ implementing EventSubscriberInterface.
        $appPath = $prefix . 'app';
        if (! in_array('app', $this->ignoredScanPaths, true) && is_dir($appPath)) {
            foreach (PhpFileIterator::iterate($appPath) as $file) {
                $relative = substr($file, strlen($prefix));

                // Avoid double-violation: app/Listeners and app/Observers are already covered above.
                if (str_starts_with($relative, 'app/Listeners/') || str_starts_with($relative, 'app/Observers/')) {
                    continue;
                }

                $ast = PhpAstParser::parseFile($file);

                /** @var Node\Stmt\Class_|null $class */
                $class = PhpAstParser::finder()->findFirstInstanceOf($ast, Node\Stmt\Class_::class);
                if ($class === null || $class->name === null) {
                    continue;
                }

                foreach ($class->implements as $interface) {
                    if (str_ends_with($interface->toString(), 'EventSubscriberInterface')) {
                        $violations[] = new Violation(
                            file: $relative,
                            line: $class->getStartLine(),
                            message: sprintf(
                                'Class [%s] implements EventSubscriberInterface — implicit-execution pattern forbidden. Dispatch events explicitly from a service action instead.',
                                $class->name->name,
                            ),
                        );
                        break;
                    }
                }
            }
        }

        return new ValidatorResult(
            validator: $this->name(),
            violations: $violations,
        );
    }
}
