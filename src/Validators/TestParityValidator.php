<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Validators;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Validates that every HTTP/CLI/Queue entrypoint has a mirror test.
 *
 * Scope is "entrypoints" — controllers, console commands, jobs.
 * Services, repositories, integrations are intentionally NOT in scope:
 * their tests are encouraged but not required as part of the test-parity
 * contract, because they are exercised transitively via entrypoint tests.
 *
 * Abstract classes are skipped — frameworks ship abstract base classes
 * (e.g. `app/Http/Controllers/Controller.php`) and consumer code shouldn't
 * be required to mirror-test them. Mirrors `SingleActionControllerValidator`'s
 * abstract-skip behavior.
 */
final class TestParityValidator extends PathScanningValidator
{
    public function name(): string
    {
        return 'TestParity';
    }

    /**
     * @return list<string>
     */
    protected function scanPaths(): array
    {
        return [
            'app/Http/Controllers',
            'app/Console/Commands',
            'app/Jobs',
        ];
    }

    /**
     * @return list<Violation>
     */
    protected function checkFile(string $absolutePath): array
    {
        $relativeFromBase = $this->relativeFromBase($absolutePath);

        if (! str_starts_with($relativeFromBase, 'app/') || ! str_ends_with($relativeFromBase, '.php')) {
            return [];
        }

        if ($this->isAbstractClass($absolutePath)) {
            return [];
        }

        $withoutAppPrefix = substr($relativeFromBase, 4);
        $withoutExt = substr($withoutAppPrefix, 0, -4);

        $featureMirror = $this->absolutePath('tests/Feature/' . $withoutExt . 'Test.php');
        $unitMirror = $this->absolutePath('tests/Unit/' . $withoutExt . 'Test.php');

        if (is_file($featureMirror) || is_file($unitMirror)) {
            return [];
        }

        return [
            new Violation(
                file: $relativeFromBase,
                line: null,
                message: sprintf(
                    'No matching test found. Expected at tests/Feature/%sTest.php or tests/Unit/%sTest.php.',
                    $withoutExt,
                    $withoutExt,
                ),
            ),
        ];
    }

    private function isAbstractClass(string $absolutePath): bool
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $contents = (string) file_get_contents($absolutePath);
        $ast = $parser->parse($contents) ?? [];

        $finder = new NodeFinder();
        /** @var Node\Stmt\Class_|null $class */
        $class = $finder->findFirstInstanceOf($ast, Node\Stmt\Class_::class);

        return $class !== null && $class->isAbstract();
    }
}
