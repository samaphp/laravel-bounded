<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Validators;

use PhpParser\Node;

/**
 * Validates two contracts on application entrypoints: mirror tests exist,
 * and entrypoints live at canonical paths.
 *
 * **Two-layer detection.** Each category (controllers, commands, jobs) runs
 * up to two passes:
 *
 *   1. **Canonical-folder scan (mirror tests).** Every concrete class under
 *      the canonical folder (`app/Http/Controllers/`, `app/Console/Commands/`,
 *      `app/Jobs/`) must have a mirror test. This pass is parentage-agnostic
 *      — it doesn't care whether the class extends `Illuminate\Console\Command`
 *      directly, extends a vendor base class (Spatie, Lorisleiva, …), or
 *      doesn't `extends` anything at all. If you put a file in the canonical
 *      folder, you need a test for it.
 *
 *   2. **Cross-`app/` misplacement scan (commands and jobs only).** Every
 *      concrete class anywhere under `app/` that transitively extends
 *      `Illuminate\Console\Command` (commands) or transitively implements
 *      `Illuminate\Contracts\Queue\ShouldQueue` (jobs) must live under the
 *      canonical folder; one outside is flagged as misplaced. Controllers
 *      have no uniform "controller" type to detect, so this pass does not
 *      apply to controllers.
 *
 * **Empty by default = silent.** Fresh Laravel scaffolds don't ship
 * `app/Console/Commands` or `app/Jobs`. If the canonical folder is missing
 * or empty AND no command/job class lives elsewhere in `app/`, this
 * validator stays silent. The moment either signal appears, it fires.
 * `SingleActionControllerValidator` separately enforces the "controllers
 * folder is non-empty" expectation; this validator does not duplicate that.
 *
 * **Documented limitations of the misplacement scan:**
 *
 *   - Parent chains are walked only across files inside `app/`. A class at
 *     a non-canonical path that extends a vendor base (e.g. Spatie's
 *     `ShortScheduleCommand`) will not be detected — the walk dies at the
 *     vendor boundary. Move such classes into the canonical folder; the
 *     folder scan then catches them regardless of parentage.
 *
 *   - Interface inheritance is not walked. A class implementing some custom
 *     `MyJobInterface extends ShouldQueue` outside `app/Jobs/` is not
 *     detected. Declare `ShouldQueue` directly, or move into `app/Jobs/`.
 *
 *   - Aliased imports (`use Foo\Bar as Baz`) are resolved correctly via the
 *     file's import table — no special handling required by consumers.
 *
 * Abstract classes are skipped from the mirror-test requirement and the
 * misplacement check — they are bases, not entrypoints.
 */
final class TestParityValidator implements ValidatorInterface, SupportsStrictMode
{
    private const COMMAND_BASE = 'Illuminate\\Console\\Command';
    private const JOB_INTERFACE = 'Illuminate\\Contracts\\Queue\\ShouldQueue';

    private const CATEGORY_CONTROLLERS = 'app/Http/Controllers';
    private const CATEGORY_COMMANDS = 'app/Console/Commands';
    private const CATEGORY_JOBS = 'app/Jobs';

    private bool $strict = false;

    /**
     * @param  list<string>  $ignoredScanPaths  Categories to skip; the path
     *     names match the canonical folders (`app/Http/Controllers`,
     *     `app/Console/Commands`, `app/Jobs`). Entry means "I don't want
     *     this category checked at all in this project."
     */
    public function __construct(
        private readonly string $basePath,
        private readonly array $ignoredScanPaths = [],
    ) {
    }

    public function name(): string
    {
        return 'TestParity';
    }

    /**
     * In strict mode, `ignore.paths` is bypassed — every category is checked
     * regardless of the ignore list.
     */
    public function setStrict(bool $strict): void
    {
        $this->strict = $strict;
    }

    public function validate(): ValidatorResult
    {
        $needControllers = $this->categoryActive(self::CATEGORY_CONTROLLERS);
        $needCommands = $this->categoryActive(self::CATEGORY_COMMANDS);
        $needJobs = $this->categoryActive(self::CATEGORY_JOBS);

        if (! $needControllers && ! $needCommands && ! $needJobs) {
            return new ValidatorResult(validator: $this->name());
        }

        $classMap = $this->buildClassMap();
        $violations = [];

        if ($needControllers) {
            array_push($violations, ...$this->checkCanonicalFolder(self::CATEGORY_CONTROLLERS, $classMap));
        }
        if ($needCommands) {
            array_push($violations, ...$this->checkCanonicalFolder(self::CATEGORY_COMMANDS, $classMap));
        }
        if ($needJobs) {
            array_push($violations, ...$this->checkCanonicalFolder(self::CATEGORY_JOBS, $classMap));
        }

        if ($needCommands) {
            array_push($violations, ...$this->checkMisplacement(
                classMap: $classMap,
                matcher: fn (string $fqn): bool => $this->isTransitiveCommand($fqn, $classMap),
                canonicalPath: self::CATEGORY_COMMANDS,
                label: 'Console command',
                baseTypeFqn: self::COMMAND_BASE,
            ));
        }
        if ($needJobs) {
            array_push($violations, ...$this->checkMisplacement(
                classMap: $classMap,
                matcher: fn (string $fqn): bool => $this->isTransitiveJob($fqn, $classMap),
                canonicalPath: self::CATEGORY_JOBS,
                label: 'Job',
                baseTypeFqn: self::JOB_INTERFACE,
            ));
        }

        return new ValidatorResult(validator: $this->name(), violations: $violations);
    }

    private function categoryActive(string $path): bool
    {
        if ($this->strict) {
            return true;
        }

        return ! in_array($path, $this->ignoredScanPaths, true);
    }

    /**
     * Folder scan: every concrete class under the canonical folder needs a
     * mirror test. Parentage-agnostic — the folder is the contract.
     *
     * Trait/enum/interface-only files in canonical folders are silently
     * skipped — they do not appear in the class map. A trait at
     * `app/Jobs/Helpers/MyTrait.php` is treated as a helper, not an
     * entrypoint. Documented relaxation from the previous filename-based
     * scan (which required mirror tests for every `.php` file regardless
     * of whether it declared a class).
     *
     * @param  array<string, ClassInfo>  $classMap
     * @return list<Violation>
     */
    private function checkCanonicalFolder(string $relativeFolder, array $classMap): array
    {
        $absoluteFolder = rtrim($this->absolutePath($relativeFolder), '/') . '/';
        $violations = [];

        foreach ($classMap as $info) {
            if (! str_starts_with($info->absolutePath, $absoluteFolder)) {
                continue;
            }
            if ($info->isAbstract) {
                continue;
            }
            $missing = $this->missingMirrorTest($info->absolutePath);
            if ($missing !== null) {
                $violations[] = $missing;
            }
        }

        return $violations;
    }

    /**
     * Misplacement scan: a class that transitively matches the entrypoint
     * predicate (`extends Command`, `implements ShouldQueue`) but lives
     * outside the canonical folder. Classes inside the canonical folder are
     * skipped here — the folder scan handles them, parentage-agnostic.
     *
     * @param  array<string, ClassInfo>  $classMap
     * @param  callable(string): bool  $matcher
     * @return list<Violation>
     */
    private function checkMisplacement(
        array $classMap,
        callable $matcher,
        string $canonicalPath,
        string $label,
        string $baseTypeFqn,
    ): array {
        $violations = [];
        $canonicalAbsolute = rtrim($this->absolutePath($canonicalPath), '/') . '/';

        foreach ($classMap as $fqn => $info) {
            if ($info->isAbstract) {
                continue;
            }
            if (str_starts_with($info->absolutePath, $canonicalAbsolute)) {
                continue;
            }
            if (! $matcher($fqn)) {
                continue;
            }

            $violations[] = new Violation(
                file: $this->relativeFromBase($info->absolutePath),
                line: $info->classStartLine,
                message: sprintf(
                    '%s [%s] (%s) must live under [%s/]. Move the class into the canonical folder so the package can validate placement, mirror tests, and downstream rules.',
                    $label,
                    $fqn,
                    $baseTypeFqn,
                    $canonicalPath,
                ),
            );
        }

        return $violations;
    }

    private function missingMirrorTest(string $absolutePath): ?Violation
    {
        $relative = $this->relativeFromBase($absolutePath);
        if (! str_starts_with($relative, 'app/') || ! str_ends_with($relative, '.php')) {
            return null;
        }

        $withoutAppPrefix = substr($relative, 4);
        $withoutExt = substr($withoutAppPrefix, 0, -4);

        $featureMirror = $this->absolutePath('tests/Feature/' . $withoutExt . 'Test.php');
        $unitMirror = $this->absolutePath('tests/Unit/' . $withoutExt . 'Test.php');

        if (is_file($featureMirror) || is_file($unitMirror)) {
            return null;
        }

        return new Violation(
            file: $relative,
            line: null,
            message: sprintf(
                'No matching test found. Expected at tests/Feature/%sTest.php or tests/Unit/%sTest.php.',
                $withoutExt,
                $withoutExt,
            ),
        );
    }

    /**
     * @return array<string, ClassInfo>
     */
    private function buildClassMap(): array
    {
        $appDir = $this->absolutePath('app');
        if (! is_dir($appDir)) {
            return [];
        }

        $map = [];
        foreach (PhpFileIterator::iterate($appDir) as $file) {
            $info = $this->extractClassInfo($file);
            if ($info !== null) {
                $map[$info->fqn] = $info;
            }
        }

        return $map;
    }

    private function extractClassInfo(string $file): ?ClassInfo
    {
        $ast = PhpAstParser::parseFile($file);

        $namespace = $this->extractNamespace($ast);
        $imports = $this->extractImports($ast);

        /** @var Node\Stmt\Class_|null $class */
        $class = PhpAstParser::finder()->findFirstInstanceOf($ast, Node\Stmt\Class_::class);
        if ($class === null || $class->name === null) {
            return null;
        }

        $shortName = $class->name->name;
        $fqn = $namespace === null ? $shortName : $namespace . '\\' . $shortName;

        $parentFqn = $class->extends !== null
            ? $this->resolveName($class->extends, $imports, $namespace)
            : null;

        $interfaces = [];
        foreach ($class->implements as $iface) {
            $interfaces[] = $this->resolveName($iface, $imports, $namespace);
        }

        return new ClassInfo(
            absolutePath: $file,
            fqn: $fqn,
            parentFqn: $parentFqn,
            interfaces: $interfaces,
            isAbstract: $class->isAbstract(),
            classStartLine: $class->getStartLine(),
        );
    }

    /**
     * @param  list<Node\Stmt>  $ast
     */
    private function extractNamespace(array $ast): ?string
    {
        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                return $node->name?->toString();
            }
        }

        return null;
    }

    /**
     * Build a map of `local-alias => fully-qualified-name` from the file's
     * top-level `use` statements (the only kind we care about — `use Foo\Bar`
     * normalises to `Bar => Foo\Bar`; aliased forms `use Foo\Bar as B` give
     * `B => Foo\Bar`). Group uses are expanded by php-parser into individual
     * `UseUse` nodes, so we don't handle them specially.
     *
     * @param  list<Node\Stmt>  $ast
     * @return array<string, string>
     */
    private function extractImports(array $ast): array
    {
        $statements = $ast;
        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                $statements = $node->stmts;
                break;
            }
        }

        $imports = [];
        foreach ($statements as $stmt) {
            if (! $stmt instanceof Node\Stmt\Use_) {
                continue;
            }
            if ($stmt->type !== Node\Stmt\Use_::TYPE_NORMAL) {
                continue;
            }
            foreach ($stmt->uses as $use) {
                $fqn = $use->name->toString();
                $alias = $use->alias?->name ?? $use->name->getLast();
                $imports[$alias] = $fqn;
            }
        }

        return $imports;
    }

    /**
     * @param  array<string, string>  $imports
     */
    private function resolveName(Node\Name $name, array $imports, ?string $namespace): string
    {
        if ($name->isFullyQualified()) {
            return ltrim($name->toString(), '\\');
        }

        $parts = $name->getParts();
        $first = $parts[0];

        if (isset($imports[$first])) {
            $rest = array_slice($parts, 1);

            return $rest === []
                ? $imports[$first]
                : $imports[$first] . '\\' . implode('\\', $rest);
        }

        if ($namespace === null) {
            return $name->toString();
        }

        return $namespace . '\\' . $name->toString();
    }

    /**
     * @param  array<string, ClassInfo>  $map
     */
    private function isTransitiveCommand(string $fqn, array $map): bool
    {
        $visited = [];
        $current = $fqn;

        while ($current !== null && ! isset($visited[$current])) {
            if ($current === self::COMMAND_BASE) {
                return true;
            }
            $visited[$current] = true;
            $info = $map[$current] ?? null;
            $current = $info?->parentFqn;
        }

        return false;
    }

    /**
     * @param  array<string, ClassInfo>  $map
     */
    private function isTransitiveJob(string $fqn, array $map): bool
    {
        $visited = [];
        $current = $fqn;

        while ($current !== null && ! isset($visited[$current])) {
            $info = $map[$current] ?? null;
            if ($info !== null && in_array(self::JOB_INTERFACE, $info->interfaces, true)) {
                return true;
            }
            $visited[$current] = true;
            $current = $info?->parentFqn;
        }

        return false;
    }

    private function absolutePath(string $relative): string
    {
        return rtrim($this->basePath, '/') . '/' . ltrim($relative, '/');
    }

    private function relativeFromBase(string $absolute): string
    {
        $prefix = rtrim($this->basePath, '/') . '/';
        if (! str_starts_with($absolute, $prefix)) {
            return $absolute;
        }

        return substr($absolute, strlen($prefix));
    }
}
