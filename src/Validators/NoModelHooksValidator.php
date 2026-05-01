<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Validators;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Anti-pattern detector: forbids overrides of Eloquent model lifecycle
 * hooks (`boot()`, `booted()`).
 *
 * Lifecycle hooks fire on model events without a visible call site.
 * Reading a service can't tell you what runs when ->save() / ::create()
 * is invoked. Move the logic into an explicit service method instead.
 *
 * Scans `app/Models/`. Missing or empty `app/Models` is the *success*
 * case (consumer doesn't use Eloquent or has no models yet). This
 * validator does not inherit `PathScanningValidator` because the
 * missing/empty semantics are inverted from that base.
 *
 * Heuristic: any class in `app/Models/` declaring `boot()` or `booted()`
 * is flagged, regardless of whether it actually extends `Model`. Files
 * misplaced under `app/Models/` should be moved.
 */
final class NoModelHooksValidator implements ValidatorInterface
{
    private const SCAN_PATH = 'app/Models';

    private const FORBIDDEN_METHODS = ['boot', 'booted'];

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
        return 'NoModelHooks';
    }

    public function validate(): ValidatorResult
    {
        if (in_array(self::SCAN_PATH, $this->ignoredScanPaths, true)) {
            return new ValidatorResult(validator: $this->name());
        }

        $prefix = rtrim($this->basePath, '/') . '/';
        $absolutePath = $prefix . self::SCAN_PATH;

        if (! is_dir($absolutePath)) {
            return new ValidatorResult(validator: $this->name());
        }

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $finder = new NodeFinder();
        $violations = [];

        foreach (PhpFileIterator::iterate($absolutePath) as $file) {
            $relative = substr($file, strlen($prefix));
            $contents = (string) file_get_contents($file);
            $ast = $parser->parse($contents) ?? [];

            /** @var Node\Stmt\Class_|null $class */
            $class = $finder->findFirstInstanceOf($ast, Node\Stmt\Class_::class);
            if ($class === null || $class->name === null) {
                continue;
            }

            foreach ($class->getMethods() as $method) {
                $methodName = $method->name->name;
                if (in_array($methodName, self::FORBIDDEN_METHODS, true)) {
                    $violations[] = new Violation(
                        file: $relative,
                        line: $method->getStartLine(),
                        message: sprintf(
                            'Model [%s] overrides [%s()] — model lifecycle hooks are implicit-execution patterns. Move the logic into an explicit service method.',
                            $class->name->name,
                            $methodName,
                        ),
                    );
                }
            }
        }

        return new ValidatorResult(
            validator: $this->name(),
            violations: $violations,
        );
    }
}
