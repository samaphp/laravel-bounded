<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Coverage;

use Samaphp\LaravelBounded\Validators\PhpFileIterator;

/**
 * Transaction coverage gate.
 *
 * **Detection conventions** (greps source for these patterns):
 *   - `Transaction::run(`            — static facade call
 *   - `->transaction->run(`          — instance call via property named `$transaction`
 *
 * The instance-call pattern requires the property to be named exactly
 * `$transaction`. If you inject the service as `Transaction $tx` and call
 * `$this->tx->run(...)`, this gate will miss it. Stick with `$transaction`
 * as the property name across all services that use the Transaction
 * service — it's the one undocumented coupling, made explicit here.
 */
final class TransactionCoverageGate
{
    public function check(string $appPath, string $cloverReportPath): TransactionCoverageResult
    {
        $callSites = $this->findCallSites($appPath);
        if ($callSites === []) {
            return TransactionCoverageResult::noCallSites();
        }

        if (! is_file($cloverReportPath)) {
            return TransactionCoverageResult::reportMissing($cloverReportPath);
        }

        $covered = $this->parseClover($cloverReportPath);

        $uncovered = [];
        foreach ($callSites as [$file, $line]) {
            $resolvedPath = realpath($file) ?: $file;
            $hitCount = $covered[$resolvedPath][$line] ?? 0;
            if ($hitCount === 0) {
                $uncovered[] = [$file, $line];
            }
        }

        return new TransactionCoverageResult(
            passed: $uncovered === [],
            totalCallSites: count($callSites),
            uncoveredCallSites: $uncovered,
        );
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function findCallSites(string $appPath): array
    {
        if (! is_dir($appPath)) {
            return [];
        }

        $callSites = [];
        foreach (PhpFileIterator::iterate($appPath) as $file) {
            $contents = (string) file_get_contents($file);
            $lines = explode("\n", $contents);
            foreach ($lines as $idx => $line) {
                // Match common patterns: `Transaction::run(` (static) or
                // `->transaction->run(` (instance via property).
                if (str_contains($line, 'Transaction::run(') || str_contains($line, '->transaction->run(')) {
                    $callSites[] = [$file, $idx + 1];
                }
            }
        }

        return $callSites;
    }

    /**
     * @return array<string, array<int, int>>  filePath => [lineNumber => hitCount]
     */
    private function parseClover(string $path): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = @simplexml_load_file($path);
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return [];
        }

        $result = [];
        foreach ($xml->project->file ?? [] as $file) {
            $filePath = (string) $file['name'];
            foreach ($file->line ?? [] as $line) {
                if ((string) $line['type'] !== 'stmt') {
                    continue;
                }
                $result[$filePath][(int) $line['num']] = (int) $line['count'];
            }
        }

        return $result;
    }
}
