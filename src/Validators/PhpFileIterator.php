<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Validators;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class PhpFileIterator
{
    /**
     * Iterate every .php file under $directory recursively.
     *
     * Returns a Generator — caller decides whether to consume eagerly or
     * lazily. Yields no entries when $directory does not exist (no
     * exception, no problem) so callers don't need a guard.
     *
     * @return \Generator<int, string>
     */
    public static function iterate(string $directory): \Generator
    {
        if (! is_dir($directory)) {
            return;
        }

        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iter as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }

    /**
     * Collect every .php file under $directory recursively into a sorted list.
     *
     * @return list<string>
     */
    public static function collect(string $directory): array
    {
        $files = iterator_to_array(self::iterate($directory), false);
        sort($files);

        return $files;
    }
}
