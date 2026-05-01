<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Validators;

use LogicException;

abstract class PathScanningValidator implements ValidatorInterface
{
    /**
     * @param  list<string>  $ignoredScanPaths  Scan paths to skip silently — sourced from config/bounded.php's `ignore.paths`.
     */
    public function __construct(
        protected readonly string $basePath,
        protected readonly array $ignoredScanPaths = [],
    ) {
    }

    public function validate(): ValidatorResult
    {
        $problems = [];
        $violations = [];

        foreach ($this->scanPaths() as $relativePath) {
            if (in_array($relativePath, $this->ignoredScanPaths, true)) {
                continue;
            }

            $fullPath = $this->absolutePath($relativePath);

            if (! is_dir($fullPath)) {
                $problems[] = $this->missingPathProblem($relativePath);

                continue;
            }

            $files = PhpFileIterator::collect($fullPath);

            if ($files === []) {
                $problems[] = $this->emptyPathProblem($relativePath);

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
                'Scan path [%s] does not exist. This package assumes Laravel\'s standard layout — see README\'s preconditions.',
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
