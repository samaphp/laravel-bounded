<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Validators;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Shared parser/finder helpers so each validator doesn't re-instantiate
 * the nikic/php-parser pipeline. Mirrors the pattern of PhpFileIterator —
 * one place to change parser version, error handling, or memoization.
 */
final class PhpAstParser
{
    private static ?Parser $parser = null;
    private static ?NodeFinder $finder = null;

    /**
     * @return list<Node\Stmt>
     */
    public static function parseFile(string $absolutePath): array
    {
        return self::parser()->parse((string) file_get_contents($absolutePath)) ?? [];
    }

    public static function finder(): NodeFinder
    {
        return self::$finder ??= new NodeFinder();
    }

    private static function parser(): Parser
    {
        return self::$parser ??= (new ParserFactory)->createForNewestSupportedVersion();
    }
}
