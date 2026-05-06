<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Validators;

use LogicException;

abstract class PathScanningValidator implements ValidatorInterface, SupportsStrictMode
{
    private bool $strict = false;

    /**
     * @param  list<string>  $ignoredScanPaths  Scan paths to skip — sourced from config/bounded.php's `ignore.paths`.
     */
    public function __construct(
        protected readonly string $basePath,
        protected readonly array $ignoredScanPaths = [],
    ) {
    }

    /**
     * Toggle strict mode.
     *
     * In strict mode, ignored paths are still scanned for file-level
     * violations if they exist (so devs can't hide violations by adding
     * a path to ignore.paths). But the structural problems
     * ScanPathMissing / ScanPathEmpty are still suppressed for ignored
     * paths — `ignore.paths` legitimately says "this category isn't
     * applicable to my project," and that statement is unchanged by
     * strict mode.
     */
    public function setStrict(bool $strict): void
    {
        $this->strict = $strict;
    }

    public function validate(): ValidatorResult
    {
        $problems = [];
        $violations = [];

        foreach ($this->scanPaths() as $relativePath) {
            $isIgnored = in_array($relativePath, $this->ignoredScanPaths, true);

            if ($isIgnored && ! $this->strict) {
                continue;
            }

            $fullPath = $this->absolutePath($relativePath);

            if (! is_dir($fullPath)) {
                if (! $isIgnored) {
                    $problems[] = $this->missingPathProblem($relativePath);
                }

                continue;
            }

            $files = PhpFileIterator::collect($fullPath);

            if ($files === []) {
                if (! $isIgnored) {
                    $problems[] = $this->emptyPathProblem($relativePath);
                }

                continue;
            }

            foreach ($files as $absolutePath) {
                array_push($violations, ...$this->checkFile($absolutePath));
            }
        }

        return new ValidatorResult(
            validator: $this->name(),
            violations: $violations,
            problems: $problems,
        );
    }

    /**
     * Relative paths under basePath to scan.
     *
     * @return list<string>
     */
    abstract protected function scanPaths(): array;

    /**
     * Per-file rule. Return all violations found in the file.
     *
     * @return list<Violation>
     */
    abstract protected function checkFile(string $absolutePath): array;

    protected function missingPathProblem(string $relativePath): Problem
    {
        return new Problem(
            kind: ProblemKind::ScanPathMissing,
            context: $relativePath,
            message: sprintf(
                'Path [%s] does not exist. If your project doesn\'t use this category yet (e.g. no console commands, no queued jobs), add [%s] to `ignore.paths` in config/bounded.php to suppress this. If your project has a non-standard layout, that\'s the other reason this fires.',
                $relativePath,
                $relativePath,
            ),
        );
    }

    protected function emptyPathProblem(string $relativePath): Problem
    {
        return new Problem(
            kind: ProblemKind::ScanPathEmpty,
            context: $relativePath,
            message: sprintf(
                'No files matched [%s]. If this project doesn\'t use this category (e.g., no jobs because no queues), suppress by adding [%s] to config/bounded.php\'s `ignore.paths`.',
                $relativePath,
                $relativePath,
            ),
        );
    }

    protected function absolutePath(string $relativePath): string
    {
        return rtrim($this->basePath, '/') . '/' . ltrim($relativePath, '/');
    }

    protected function relativeFromBase(string $absolutePath): string
    {
        $prefix = rtrim($this->basePath, '/') . '/';

        if (! str_starts_with($absolutePath, $prefix)) {
            throw new LogicException(sprintf(
                'Path [%s] is outside basePath [%s]; this should be unreachable. Check how the path was constructed.',
                $absolutePath,
                $this->basePath,
            ));
        }

        return substr($absolutePath, strlen($prefix));
    }
}
